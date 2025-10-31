<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 超时保护执行结果
 *
 * 统一的执行结果数据结构，用于包装 TimeoutGuard 的执行结果
 */
final readonly class ExecutionResult
{
    /**
     * @param mixed $content 执行结果内容
     * @param array<string, mixed> $metadata 元数据
     */
    public function __construct(
        public bool $success,
        public mixed $content = null,
        public array $metadata = [],
        public ?\Throwable $error = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function getErrorMessage(): string
    {
        return $this->error?->getMessage() ?? '';
    }
}
