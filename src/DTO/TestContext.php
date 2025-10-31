<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\DTO;

/**
 * 统一的测试上下文 - 消除所有边界情况
 *
 * 按照Linus的"数据结构至上"原则，这是整个测试系统的核心数据结构。
 * 所有测试逻辑都围绕这个结构设计，确保一致性和可预测性。
 */
final readonly class TestContext
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $template,
        public array $parameters,
        public int $timeoutMs = 5000,
        public string $engine = 'twig',
    ) {
    }
}
