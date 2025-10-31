<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

/**
 * 测试服务接口
 *
 * 定义提示词测试和预览功能的核心契约
 */
interface TestingServiceInterface
{
    /**
     * 解析模板并提取参数
     * @param string $template 模板内容
     * @return array<string, array<string, mixed>> 参数列表，格式：['param_name' => ['type' => 'string', 'required' => true]]
     */
    public function extractParameters(string $template): array;

    /**
     * 渲染模板
     * @param string $template 模板内容
     * @param array<string, mixed> $parameters 参数键值对
     */
    public function renderTemplate(string $template, array $parameters): string;

    /**
     * 获取提示词的测试数据
     * @param int $promptId 提示词ID
     * @param int|null $version 版本号，null表示当前版本
     * @return array<string, mixed>
     */
    public function getTestData(int $promptId, ?int $version = null): array;

    /**
     * 执行测试并返回结果
     * @param int $promptId 提示词ID
     * @param int $version 版本号
     * @param array<string, mixed> $parameters 参数
     * @param string|null $customTemplate 自定义模板（可选）
     * @return array<string, mixed>
     */
    public function executeTest(int $promptId, int $version, array $parameters, ?string $customTemplate = null): array;
}
