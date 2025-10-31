<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ParseResult;

/**
 * 参数提取器接口 - 可插拔的参数提取策略
 *
 * 支持不同类型的参数提取逻辑，实现真正的可扩展性
 */
interface ParameterExtractorInterface
{
    /**
     * 提取器名称
     */
    public function getName(): string;

    /**
     * 从模板中提取参数定义
     */
    public function extractParameters(string $template): ParseResult;

    /**
     * 检查是否支持指定的模板格式
     */
    public function supports(string $template): bool;

    /**
     * 获取支持的语法特性
     *
     * @return array<string>
     */
    public function getSupportedPatterns(): array;
}
