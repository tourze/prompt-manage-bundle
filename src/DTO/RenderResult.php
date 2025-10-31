<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 模板渲染结果
 *
 * 统一的渲染结果数据结构
 */
final readonly class RenderResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $success,
        public string $content = '',
        public array $metadata = [],
        public ?\Throwable $error = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getContentLength(): int
    {
        return strlen($this->content);
    }
}
