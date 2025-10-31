<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * Twig模板引擎 - 默认的强大模板引擎
 *
 * 基于Symfony生态系统中最成熟的Twig引擎
 */
final class TwigEngine implements TemplateEngineInterface
{
    private Environment $twig;

    public function __construct()
    {
        // 创建安全的Twig环境
        $loader = new ArrayLoader([]);
        $this->twig = new Environment($loader, [
            'strict_variables' => true,
            'autoescape' => 'html',
            'optimizations' => -1,
        ]);
    }

    public function getName(): string
    {
        return 'twig';
    }

    public function parseTemplate(string $template): ParseResult
    {
        try {
            // 先验证语法
            $validation = $this->validateTemplate($template);
            if (!$validation->isValid()) {
                return new ParseResult(
                    success: false,
                    warnings: $validation->warnings,
                    error: new \RuntimeException('Template syntax validation failed: ' . implode(', ', $validation->errors))
                );
            }

            // 提取变量
            $variables = $this->extractVariables($template);
            $parameters = [];

            foreach ($variables as $variable) {
                $parameters[$variable] = [
                    'type' => 'string',
                    'required' => true,
                ];
            }

            return new ParseResult(
                success: true,
                parameters: $parameters
            );
        } catch (\Throwable $e) {
            return new ParseResult(
                success: false,
                error: $e
            );
        }
    }

    public function validateTemplate(string $template): ValidationResult
    {
        try {
            $this->twig->createTemplate($template);

            return new ValidationResult(
                valid: true,
                metadata: ['engine' => 'twig']
            );
        } catch (SyntaxError $e) {
            return new ValidationResult(
                valid: false,
                errors: [$e->getMessage()],
                metadata: [
                    'engine' => 'twig',
                    'line' => $e->getTemplateLine(),
                ]
            );
        } catch (\Throwable $e) {
            return new ValidationResult(
                valid: false,
                errors: [$e->getMessage()],
                metadata: ['engine' => 'twig']
            );
        }
    }

    /**
     * 从模板中提取变量名
     *
     * @return array<string>
     */
    private function extractVariables(string $template): array
    {
        $variables = [];

        // 使用更精确的正则表达式，只匹配纯变量而不匹配表达式
        // 匹配 {{ variable }} 或 {{ variable.property }}，但不匹配字符串、数字、函数调用等
        preg_match_all('/\{\{\s*(?![\'"0-9])([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\s*\}\}/', $template, $matches);

        if ([] !== $matches[1]) {
            foreach ($matches[1] as $match) {
                // 额外过滤：确保不包含函数调用、运算符等
                if (!str_contains($match, '(')
                    && !str_contains($match, ')')
                    && !str_contains($match, '"')
                    && !str_contains($match, "'")
                    && 0 === preg_match('/\s/', $match)) {
                    // 对于嵌套属性，只取根变量名
                    $rootVar = explode('.', $match)[0];
                    $variables[] = $rootVar;
                }
            }
        }

        return array_unique($variables);
    }

    public function render(string $template, array $parameters): RenderResult
    {
        try {
            // 创建模板
            $twigTemplate = $this->twig->createTemplate($template);

            // 渲染
            $content = $twigTemplate->render($parameters);

            return new RenderResult(
                success: true,
                content: $content,
                metadata: [
                    'engine' => 'twig',
                    'version' => $this->getVersion(),
                    'original_length' => strlen($template),
                    'result_length' => strlen($content),
                ]
            );
        } catch (RuntimeError $e) {
            return new RenderResult(
                success: false,
                content: $template,
                metadata: ['engine' => 'twig', 'error_type' => 'runtime'],
                error: $e
            );
        } catch (\Throwable $e) {
            return new RenderResult(
                success: false,
                content: $template,
                metadata: ['engine' => 'twig', 'error_type' => 'general'],
                error: $e
            );
        }
    }

    public function getVersion(): string
    {
        return Environment::VERSION;
    }

    public function isAvailable(): bool
    {
        return class_exists(Environment::class);
    }

    public function getConfiguration(): array
    {
        return [
            'engine' => 'twig',
            'version' => $this->getVersion(),
            'strict_variables' => true,
            'autoescape' => 'html',
            'optimizations' => -1,
            'features' => $this->getSupportedFeatures(),
        ];
    }

    public function getSupportedFeatures(): array
    {
        return [
            'variables',
            'filters',
            'functions',
            'conditionals',
            'loops',
            'includes',
            'macros',
            'inheritance',
            'auto_escape',
            'strict_variables',
        ];
    }
}
