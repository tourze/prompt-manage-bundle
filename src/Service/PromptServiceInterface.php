<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;

interface PromptServiceInterface
{
    /**
     * 创建新提示词（含初始版本）
     * @param string $name 名称
     * @param string $content 内容模板
     * @param string|null $projectName 项目名称
     * @param string[] $tagNames 标签名称数组
     * @param string|null $createdBy 创建人ID
     * @param string|null $changeNote 变更备注
     */
    public function createPrompt(
        string $name,
        string $content,
        ?string $projectName = null,
        array $tagNames = [],
        ?string $createdBy = null,
        ?string $changeNote = null,
        ?string $visibility = null,
    ): Prompt;

    /**
     * 更新提示词内容（生成新版本）
     * @param int $promptId 提示词ID
     * @param string $content 新内容
     * @param string $changeNote 变更备注（必填）
     * @param string|null $updatedBy 修改人ID
     */
    public function updatePrompt(
        int $promptId,
        string $content,
        string $changeNote,
        ?string $updatedBy = null,
    ): PromptVersion;

    /**
     * 切换到指定版本（复制历史版本内容生成新版本）
     * @param int $promptId 提示词ID
     * @param int $targetVersion 目标版本号
     * @param string|null $operatorId 操作人ID
     */
    public function switchToVersion(
        int $promptId,
        int $targetVersion,
        ?string $operatorId = null,
    ): PromptVersion;

    /**
     * 根据名称获取提示词内容（当前版本）
     * @param string $name 提示词名称
     */
    public function getPromptContent(string $name): ?string;

    /**
     * 从模板内容中解析占位符变量
     * @param string $content 模板内容
     * @return string[] 占位符变量列表
     */
    public function extractPlaceholders(string $content): array;

    /**
     * 使用参数渲染模板
     * @param string $template 模板内容
     * @param array<string, mixed> $params 参数映射
     */
    public function renderTemplate(string $template, array $params = []): string;

    /**
     * 删除提示词
     * @param int $promptId 提示词ID
     * @param string|null $deletedBy 删除人ID
     */
    public function deletePrompt(int $promptId, ?string $deletedBy = null): void;

    /**
     * 获取提示词的所有版本
     * @param int $promptId 提示词ID
     * @return PromptVersion[] 版本列表，按版本号倒序排列
     */
    public function getPromptVersions(int $promptId): array;
}
