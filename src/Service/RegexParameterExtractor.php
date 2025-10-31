<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ParseResult;

/**
 * 正则表达式参数提取器 - 默认的参数提取实现
 *
 * 使用正则表达式匹配各种参数模式，支持多种语法格式
 */
final readonly class RegexParameterExtractor implements ParameterExtractorInterface
{
    private const PATTERNS = [
        // Twig风格: {{ variable }} - 优先处理双大括号，避免被简单模式匹配
        'twig' => '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_\.]*)\s*\}\}/',
        // 简单风格: {variable} - 在twig之后处理，避免冲突
        'simple' => '/\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}/',
        // 百分号风格: %variable%
        'percent' => '/%([a-zA-Z_][a-zA-Z0-9_]*)%/',
        // 美元符号风格: $variable
        'dollar' => '/\$([a-zA-Z_][a-zA-Z0-9_]*)/',
    ];

    public function getName(): string
    {
        return 'regex';
    }

    public function extractParameters(string $template): ParseResult
    {
        try {
            $extractionData = $this->processAllPatterns($template);

            return new ParseResult(
                success: true,
                parameters: $extractionData['parameters'],
                warnings: $extractionData['warnings']
            );
        } catch (\Throwable $e) {
            return new ParseResult(
                success: false,
                error: $e
            );
        }
    }

    /**
     * 处理所有模式并返回提取的数据
     * @return array{parameters: array<string, array<string, mixed>>, warnings: array<string>}
     */
    private function processAllPatterns(string $template): array
    {
        $allParameters = [];
        $warnings = [];

        foreach (self::PATTERNS as $type => $pattern) {
            $patternResult = $this->processPattern($template, $type, $pattern);

            // 只添加不存在的参数，避免覆盖更特异的模式结果
            foreach ($patternResult['parameters'] as $key => $value) {
                if (!isset($allParameters[$key])) {
                    $allParameters[$key] = $value;
                }
            }

            $warnings = array_merge($warnings, $patternResult['warnings']);
        }

        return [
            'parameters' => $allParameters,
            'warnings' => $warnings,
        ];
    }

    /**
     * 处理单个正则模式的匹配和参数提取
     * @return array{parameters: array<string, array<string, mixed>>, warnings: array<string>}
     */
    private function processPattern(string $template, string $type, string $pattern): array
    {
        $matches = [];
        $result = preg_match_all($pattern, $template, $matches);

        if (false === $result) {
            return [
                'parameters' => [],
                'warnings' => ["Failed to apply {$type} pattern"],
            ];
        }

        if ([] === $matches[1]) {
            return [
                'parameters' => [],
                'warnings' => [],
            ];
        }

        return [
            'parameters' => $this->extractVariablesFromMatches($matches[1], $type),
            'warnings' => [],
        ];
    }

    /**
     * 从匹配结果中提取变量
     * @param array<string> $variables
     * @return array<string, array<string, mixed>>
     */
    private function extractVariablesFromMatches(array $variables, string $type): array
    {
        $parameters = [];

        foreach ($variables as $variable) {
            // 处理嵌套属性（如 user.name）
            $cleanVariable = explode('.', $variable)[0];

            // 避免覆盖已找到的更具体的模式
            if (!isset($parameters[$cleanVariable])) {
                $parameters[$cleanVariable] = [
                    'type' => 'string',
                    'required' => true,
                    'pattern' => $type,
                    'source' => $variable,
                ];
            }
        }

        return $parameters;
    }

    public function supports(string $template): bool
    {
        // 检查是否包含任何支持的参数模式
        foreach (self::PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $template)) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedPatterns(): array
    {
        return array_keys(self::PATTERNS);
    }
}
