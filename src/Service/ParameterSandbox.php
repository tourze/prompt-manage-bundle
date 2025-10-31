<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ValidationResult;

/**
 * 参数沙箱 - 安全隔离用户输入，防止注入攻击
 *
 * Linus: "永远不要信任用户输入，即使是最简单的字符串"
 * 提供多层防护：类型验证、内容过滤、长度限制、危险模式检测
 */
final readonly class ParameterSandbox
{
    private const MAX_PARAM_LENGTH = 10000;
    private const DANGEROUS_PATTERNS = [
        // 需要清理的危险模式
        // 模板注入
        '/\{\{.*?\}\}/' => '[TEMPLATE]',
        '/\{%.*?%\}/' => '[TEMPLATE]',
        // PHP代码注入
        '/\<\?php.*?\?>/s' => '[PHP_CODE]',
        '/\<\?=.*?\?>/s' => '[PHP_CODE]',
        // JavaScript注入
        '/javascript:/i' => '[JS]',
        // 路径遍历
        '/\.\.[\/\\\]/' => '[PATH]',
    ];

    private const SQL_INJECTION_PATTERNS = [
        // SQL注入关键词
        '/\bDROP\s+TABLE\b/i' => '[SQL_DROP]',
        '/\bDELETE\s+FROM\b/i' => '[SQL_DELETE]',
        '/\bUNION\s+SELECT\b/i' => '[SQL_UNION]',
        '/\'\s*OR\s*\'\d+\'\s*=\s*\'\d+/i' => '[SQL_OR]',
        '/--\s*$/' => '[SQL_COMMENT]',
    ];

    /**
     * 清理和验证参数
     *
     * @param array<string, mixed> $parameters
     * @return array{parameters: array<string, mixed>, validation: ValidationResult}
     */
    public function sanitize(array $parameters): array
    {
        $sanitized = [];
        $errors = [];
        $warnings = [];

        foreach ($parameters as $key => $value) {
            // 验证键名
            if (!$this->isValidParameterName($key)) {
                $errors[] = "Invalid parameter name: {$key}";
                continue;
            }

            // 验证和清理值
            $result = $this->sanitizeValue($value, $key);
            if (null !== $result['value']) {
                $sanitized[$key] = $result['value'];
            } else {
                $errors[] = "Parameter '{$key}' contains dangerous content and was removed";
            }

            $warnings = array_merge($warnings, $result['warnings']);
        }

        $validation = new ValidationResult(
            valid: [] === $errors,
            errors: $errors,
            warnings: $warnings
        );

        return [
            'parameters' => $sanitized,
            'validation' => $validation,
        ];
    }

    /**
     * 验证参数名是否安全
     */
    private function isValidParameterName(string $name): bool
    {
        // 只允许字母、数字、下划线，长度1-50
        return 1 === preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/', $name);
    }

    /**
     * 清理单个参数值
     *
     * @return array{value: mixed, warnings: array<string>}
     */
    private function sanitizeValue(mixed $value, string $key): array
    {
        $warnings = [];

        // 处理非标量、非数组类型
        $normalizeResult = $this->normalizeValueType($value, $key);
        $value = $normalizeResult['value'];
        $warnings = array_merge($warnings, $normalizeResult['warnings']);

        // 处理数组类型
        if (is_array($value)) {
            $arrayResult = $this->sanitizeArrayValue($value, $key);

            return [
                'value' => $arrayResult['value'],
                'warnings' => array_merge($warnings, $arrayResult['warnings']),
            ];
        }

        $stringValue = match (true) {
            is_string($value) => $value,
            is_numeric($value) || is_bool($value) => (string) $value,
            default => '', // 其他类型转换为空字符串
        };

        $truncateResult = $this->truncateIfNeeded($stringValue, $key);
        $stringValue = $truncateResult['value'];
        $warnings = array_merge($warnings, $truncateResult['warnings']);

        $stringValue = $this->cleanDangerousPatterns($stringValue);
        $stringValue = $this->htmlEncodeValue($stringValue);

        return [
            'value' => $stringValue,
            'warnings' => $warnings,
        ];
    }

    /**
     * 将值标准化为合适的类型
     *
     * @return array{value: mixed, warnings: array<string>}
     */
    private function normalizeValueType(mixed $value, string $key): array
    {
        if (is_scalar($value) || is_array($value)) {
            return ['value' => $value, 'warnings' => []];
        }

        $warnings = ["Parameter '{$key}' of type " . gettype($value) . ' was converted to string'];

        $convertedValue = match (true) {
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_object($value) => get_class($value) . ' object',
            default => gettype($value),
        };

        return ['value' => $convertedValue, 'warnings' => $warnings];
    }

    /**
     * 处理数组类型的参数
     *
     * @param array<mixed> $value
     * @return array{value: string, warnings: array<string>}
     */
    private function sanitizeArrayValue(array $value, string $key): array
    {
        $warnings = ["Parameter '{$key}' of type array was converted to string representation"];

        $arrayResult = $this->sanitizeArray($value, $key);
        $encodedValue = (string) json_encode($arrayResult['value']);

        return [
            'value' => $encodedValue,
            'warnings' => array_merge($warnings, $arrayResult['warnings']),
        ];
    }

    /**
     * 截断过长的字符串
     *
     * @return array{value: string, warnings: array<string>}
     */
    private function truncateIfNeeded(string $stringValue, string $key): array
    {
        if (strlen($stringValue) <= self::MAX_PARAM_LENGTH) {
            return ['value' => $stringValue, 'warnings' => []];
        }

        $warnings = ["Parameter '{$key}' truncated from " . strlen($stringValue) . ' to ' . self::MAX_PARAM_LENGTH . ' characters'];
        $truncatedValue = substr($stringValue, 0, self::MAX_PARAM_LENGTH);

        return ['value' => $truncatedValue, 'warnings' => $warnings];
    }

    /**
     * 清理危险模式
     */
    private function cleanDangerousPatterns(string $stringValue): string
    {
        // 清理危险模式
        foreach (self::DANGEROUS_PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $stringValue);
            if (null !== $result) {
                $stringValue = $result;
            }
        }

        // SQL注入模式检测并清理
        foreach (self::SQL_INJECTION_PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $stringValue);
            if (null !== $result) {
                $stringValue = $result;
            }
        }

        return $stringValue;
    }

    /**
     * HTML实体编码
     */
    private function htmlEncodeValue(string $stringValue): string
    {
        return htmlspecialchars($stringValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * 递归清理数组值
     *
     * @param array<mixed> $array
     * @return array{value: array<mixed>, warnings: array<string>}
     */
    private function sanitizeArray(array $array, string $key): array
    {
        $sanitized = [];
        $count = 0;
        $warnings = [];

        foreach ($array as $arrayKey => $arrayValue) {
            if ($count >= 100) { // 限制数组大小
                $warnings[] = "Array parameter '{$key}' truncated to 100 items";
                break;
            }

            $cleanResult = $this->sanitizeValue($arrayValue, "{$key}[{$arrayKey}]");
            if (null !== $cleanResult['value']) {
                $sanitized[$arrayKey] = $cleanResult['value'];
                ++$count;
            }

            $warnings = array_merge($warnings, $cleanResult['warnings']);
        }

        return ['value' => $sanitized, 'warnings' => $warnings];
    }
}
