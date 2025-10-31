<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\RenderResult;

/**
 * 结果处理器接口 - 可插拔的结果后处理策略
 *
 * 支持对渲染结果进行各种后处理：格式化、清理、转换等
 */
interface ResultProcessorInterface
{
    /**
     * 处理器名称
     */
    public function getName(): string;

    /**
     * 处理渲染结果
     */
    public function process(RenderResult $result): RenderResult;

    /**
     * 检查是否支持处理指定类型的结果
     */
    public function supports(RenderResult $result): bool;

    /**
     * 获取处理器优先级（数字越大优先级越高）
     */
    public function getPriority(): int;
}
