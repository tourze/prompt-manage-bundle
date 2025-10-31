<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\DTO\ParseResult;
use Tourze\PromptManageBundle\DTO\RenderResult;
use Tourze\PromptManageBundle\DTO\ValidationResult;

/**
 * 模板引擎插件接口 - 可插拔设计的核心
 *
 * Linus: "接口是契约，实现是策略"
 * 允许系统支持多种模板引擎，实现真正的可扩展性
 */
interface TemplateEngineInterface
{
    /**
     * 引擎标识名称
     */
    public function getName(): string;

    /**
     * 引擎版本
     */
    public function getVersion(): string;

    /**
     * 解析模板并提取参数
     */
    public function parseTemplate(string $template): ParseResult;

    /**
     * 渲染模板
     *
     * @param array<string, mixed> $parameters
     */
    public function render(string $template, array $parameters): RenderResult;

    /**
     * 验证模板语法
     */
    public function validateTemplate(string $template): ValidationResult;

    /**
     * 检查引擎是否可用
     */
    public function isAvailable(): bool;

    /**
     * 获取支持的模板语法特性
     *
     * @return array<string>
     */
    public function getSupportedFeatures(): array;

    /**
     * 获取引擎配置选项
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array;
}
