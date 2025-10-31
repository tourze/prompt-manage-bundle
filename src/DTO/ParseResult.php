<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 模板解析结果
 *
 * 统一的解析结果数据结构，包含参数提取的完整信息
 */
final readonly class ParseResult
{
    /**
     * @param array<string, array<string, mixed>> $parameters 参数定义，格式：['param_name' => ['type' => 'string', 'required' => true]]
     * @param array<string> $warnings
     */
    public function __construct(
        public bool $success,
        public array $parameters = [],
        public array $warnings = [],
        public ?\Throwable $error = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }

    /**
     * @return array<string>
     */
    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }
}
