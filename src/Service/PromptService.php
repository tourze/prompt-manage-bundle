<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Repository\ProjectRepository;
use Tourze\PromptManageBundle\Repository\PromptRepository;
use Tourze\PromptManageBundle\Repository\PromptVersionRepository;
use Tourze\PromptManageBundle\Repository\TagRepository;

/**
 * 提示词管理服务 - 集中所有业务逻辑
 */
#[WithMonologChannel(channel: 'prompt_manage')]
class PromptService implements PromptServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PromptRepository $promptRepository,
        private readonly PromptVersionRepository $promptVersionRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 创建新的提示词
     *
     * @param string[] $tagNames
     */
    public function createPrompt(
        string $name,
        string $content,
        ?string $projectName = null,
        array $tagNames = [],
        ?string $createdBy = null,
        ?string $changeNote = null,
        ?string $visibility = null,
    ): Prompt {
        try {
            $this->entityManager->beginTransaction();

            // 检查名称是否已存在
            $existingPrompt = $this->promptRepository->findByName($name);
            if (null !== $existingPrompt) {
                throw new \InvalidArgumentException(sprintf('提示词名称 "%s" 已存在', $name));
            }

            // 创建主实体
            $prompt = new Prompt();
            $prompt->setName($name);
            $prompt->setCreatedBy($createdBy);
            if (null !== $visibility) {
                $prompt->setVisibility($visibility);
            }

            // 设置项目
            if (null !== $projectName) {
                $project = $this->findOrCreateProject($projectName);
                $prompt->setProject($project);
            }

            // 设置标签
            if ([] !== $tagNames) {
                $tags = $this->tagRepository->findOrCreateByNames(array_values($tagNames));
                if ([] !== $tags) {
                    foreach ($tags as $tag) {
                        $prompt->addTag($tag);
                    }
                }
            }

            $this->promptRepository->save($prompt);

            // 创建初始版本
            $version = new PromptVersion();
            $version->setPrompt($prompt);
            $version->setVersion(1);
            $version->setContent($content);
            $version->setChangeNote($changeNote ?? '创建初始版本');
            $version->setCreatedBy($createdBy);

            $this->promptVersionRepository->save($version);

            // 更新当前版本号
            $prompt->setCurrentVersion(1);
            $this->promptRepository->save($prompt, true);

            $this->entityManager->commit();

            $this->logger->info('创建提示词成功', [
                'prompt_id' => $prompt->getId(),
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            return $prompt;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('创建提示词失败', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 更新提示词内容（生成新版本）
     */
    public function updatePrompt(
        int $promptId,
        string $content,
        string $changeNote,
        ?string $updatedBy = null,
    ): PromptVersion {
        try {
            $this->entityManager->beginTransaction();

            $prompt = $this->promptRepository->find($promptId);
            if (null === $prompt) {
                throw new \InvalidArgumentException('提示词不存在');
            }

            // 生成新版本号
            $nextVersion = $this->promptVersionRepository->getNextVersionNumber($prompt);

            // 创建新版本
            $version = new PromptVersion();
            $version->setPrompt($prompt);
            $version->setVersion($nextVersion);
            $version->setContent($content);
            $version->setChangeNote($changeNote);
            $version->setCreatedBy($updatedBy);

            $this->promptVersionRepository->save($version);

            // 更新当前版本号
            $prompt->setCurrentVersion($nextVersion);
            $this->promptRepository->save($prompt, true);

            $this->entityManager->commit();

            $this->logger->info('更新提示词成功', [
                'prompt_id' => $promptId,
                'version' => $nextVersion,
                'updated_by' => $updatedBy,
            ]);

            return $version;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('更新提示词失败', [
                'prompt_id' => $promptId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 切换到指定版本（复制历史版本内容生成新版本）
     */
    public function switchToVersion(
        int $promptId,
        int $targetVersion,
        ?string $operatorId = null,
    ): PromptVersion {
        try {
            $this->entityManager->beginTransaction();

            $prompt = $this->promptRepository->find($promptId);
            if (null === $prompt) {
                throw new \InvalidArgumentException('提示词不存在');
            }

            $targetVersionEntity = $this->promptVersionRepository->findByPromptAndVersion($prompt, $targetVersion);
            if (null === $targetVersionEntity) {
                throw new \InvalidArgumentException(sprintf('版本 v%d 不存在', $targetVersion));
            }

            // 生成新版本号
            $nextVersion = $this->promptVersionRepository->getNextVersionNumber($prompt);

            // 复制目标版本内容到新版本
            $newVersion = new PromptVersion();
            $newVersion->setPrompt($prompt);
            $newVersion->setVersion($nextVersion);
            $newVersion->setContent($targetVersionEntity->getContent());
            $newVersion->setChangeNote(sprintf('切换到版本 v%d', $targetVersion));
            $newVersion->setCreatedBy($operatorId);

            $this->promptVersionRepository->save($newVersion);

            // 更新当前版本号
            $prompt->setCurrentVersion($nextVersion);
            $this->promptRepository->save($prompt, true);

            $this->entityManager->commit();

            $this->logger->info('版本切换成功', [
                'prompt_id' => $promptId,
                'from_version' => $prompt->getCurrentVersion(),
                'to_version' => $targetVersion,
                'new_version' => $nextVersion,
            ]);

            return $newVersion;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('版本切换失败', [
                'prompt_id' => $promptId,
                'target_version' => $targetVersion,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 根据名称获取提示词内容（当前版本）
     */
    public function getPromptContent(string $name): ?string
    {
        $prompt = $this->promptRepository->findByName($name);
        if (null === $prompt) {
            return null;
        }

        return $prompt->getCurrentVersionContent();
    }

    /**
     * 从模板内容中解析占位符变量
     *
     * @return string[] 占位符变量列表，如 ['user_input', 'context']
     */
    public function extractPlaceholders(string $content): array
    {
        $placeholders = [];

        // 先匹配双大括号语法 {{variable}}
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $doubleMatches);
        if (\count($doubleMatches[1]) > 0) {
            $placeholders = array_merge($placeholders, $doubleMatches[1]);
        }

        // 移除双大括号内容，然后匹配单大括号语法 {variable}
        $contentWithoutDouble = preg_replace('/\{\{[^}]+\}\}/', '', $content);
        if (null !== $contentWithoutDouble) {
            preg_match_all('/\{([^}]+)\}/', $contentWithoutDouble, $singleMatches);
            if (\count($singleMatches[1]) > 0) {
                $placeholders = array_merge($placeholders, $singleMatches[1]);
            }
        }

        return array_unique($placeholders);
    }

    /**
     * 使用参数渲染模板
     *
     * @param array<string, mixed> $params
     */
    public function renderTemplate(string $template, array $params = []): string
    {
        $content = $template;

        foreach ($params as $key => $value) {
            $stringValue = match (true) {
                is_string($value) => $value,
                is_numeric($value) || is_bool($value) => (string) $value,
                is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
                default => '',
            };
            // 支持双大括号语法 {{key}}
            $content = str_replace('{{' . $key . '}}', $stringValue, $content);
            // 支持单大括号语法 {key}
            $content = str_replace('{' . $key . '}', $stringValue, $content);
        }

        return $content;
    }

    /**
     * 为现有提示词添加新版本
     */
    public function addVersion(int $promptId, string $content, string $changeNote, ?string $createdBy = null): PromptVersion
    {
        try {
            $this->entityManager->beginTransaction();

            $prompt = $this->promptRepository->find($promptId);
            if (null === $prompt) {
                throw new \InvalidArgumentException('提示词不存在');
            }

            // 生成新版本号
            $nextVersion = $this->promptVersionRepository->getNextVersionNumber($prompt);

            // 创建新版本
            $version = new PromptVersion();
            $version->setPrompt($prompt);
            $version->setVersion($nextVersion);
            $version->setContent($content);
            $version->setChangeNote($changeNote);
            $version->setCreatedBy($createdBy);

            $this->promptVersionRepository->save($version);

            // 更新当前版本号
            $prompt->setCurrentVersion($nextVersion);
            $this->promptRepository->save($prompt, true);

            $this->entityManager->commit();

            $this->logger->info('添加新版本成功', [
                'prompt_id' => $promptId,
                'version' => $nextVersion,
                'created_by' => $createdBy,
            ]);

            return $version;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('添加新版本失败', [
                'prompt_id' => $promptId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 创建新版本（addVersion的别名，保持向后兼容）
     */
    public function createVersion(int $promptId, string $content, string $changeNote, ?string $createdBy = null): PromptVersion
    {
        return $this->addVersion($promptId, $content, $changeNote, $createdBy);
    }

    /**
     * 删除提示词
     */
    public function deletePrompt(int $promptId, ?string $deletedBy = null): void
    {
        try {
            $prompt = $this->promptRepository->find($promptId);
            if (null === $prompt) {
                throw new \InvalidArgumentException('提示词不存在');
            }

            $this->promptRepository->remove($prompt, true);

            $this->logger->info('删除提示词成功', [
                'prompt_id' => $promptId,
                'name' => $prompt->getName(),
                'deleted_by' => $deletedBy,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('删除提示词失败', [
                'prompt_id' => $promptId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 查找或创建项目
     */
    private function findOrCreateProject(string $name): Project
    {
        $project = $this->projectRepository->findByName($name);
        if (null === $project) {
            $project = new Project();
            $project->setName($name);
            $this->projectRepository->save($project);
        }

        return $project;
    }

    /**
     * 获取提示词的所有版本
     */
    public function getPromptVersions(int $promptId): array
    {
        $prompt = $this->promptRepository->find($promptId);
        if (null === $prompt) {
            throw new \InvalidArgumentException('提示词不存在');
        }

        return $this->promptVersionRepository->findByPromptOrderByVersion($prompt);
    }
}
