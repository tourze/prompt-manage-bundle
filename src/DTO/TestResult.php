<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 统一的测试结果 - 成功和失败都是Result
 *
 * 消除边界情况：成功和失败都使用相同的数据结构返回，
 * 简化调用方的处理逻辑，提高系统的可预测性。
 */
final readonly class TestResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $success,
        public string $content,
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
