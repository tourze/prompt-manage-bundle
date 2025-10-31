<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 模板验证结果
 *
 * 统一的验证结果数据结构，包含语法检查和安全检查的结果
 */
final readonly class ValidationResult
{
    /**
     * @param array<string> $errors 错误信息列表
     * @param array<string> $warnings 警告信息列表
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public array $metadata = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }

    /**
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        return array_merge($this->errors, $this->warnings);
    }
}
