<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Service\PromptService;

/**
 * @internal
 */
#[CoversClass(PromptService::class)]
#[RunTestsInSeparateProcesses]
final class PromptServiceVersionTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 空实现，不需要额外的设置
    }

    public function testGetPromptVersionsWithValidPrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        // 创建测试提示词
        $prompt = $promptService->createPrompt(
            'test-prompt-versions',
            'Initial content',
            null,
            [],
            'test-user',
            'Initial version'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        // 添加第二个版本
        $promptService->addVersion(
            $promptId,
            'Updated content',
            'Second version',
            'test-user'
        );

        // 获取所有版本
        $versions = $promptService->getPromptVersions($promptId);

        // 验证结果
        self::assertCount(2, $versions);
        self::assertSame(2, $versions[0]->getVersion()); // 最新版本在前
        self::assertSame(1, $versions[1]->getVersion());
        self::assertSame('Second version', $versions[0]->getChangeNote());
        self::assertSame('Initial version', $versions[1]->getChangeNote());
    }

    public function testGetPromptVersionsWithInvalidPromptId(): void
    {
        $promptService = self::getService(PromptService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('提示词不存在');

        $promptService->getPromptVersions(99999);
    }

    public function testAddVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-add-version',
            'Initial content',
            null,
            [],
            'test-user',
            'Initial version'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        $version = $promptService->addVersion(
            $promptId,
            'New version content',
            'Added new features',
            'test-user'
        );

        self::assertSame(2, $version->getVersion());
        self::assertSame('New version content', $version->getContent());
        self::assertSame('Added new features', $version->getChangeNote());
    }

    public function testCreatePrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-create-prompt',
            'Test content',
            null,
            ['param1' => 'value1'],
            'test-user',
            'Test prompt creation'
        );

        self::assertNotNull($prompt->getId());
        self::assertSame('test-create-prompt', $prompt->getName());
    }

    public function testCreateVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-create-version',
            'Initial content',
            null,
            [],
            'test-user',
            'Initial version'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        $version = $promptService->createVersion(
            $promptId,
            'Version content',
            'Version change note',
            'test-user'
        );

        self::assertSame(2, $version->getVersion());
        self::assertSame('Version content', $version->getContent());
    }

    public function testDeletePrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-delete-prompt',
            'Content to delete',
            null,
            [],
            'test-user',
            'Test deletion'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        $promptService->deletePrompt($promptId);

        $this->expectException(\InvalidArgumentException::class);
        $promptService->getPromptVersions($promptId);
    }

    public function testExtractPlaceholders(): void
    {
        $promptService = self::getService(PromptService::class);

        $placeholders = $promptService->extractPlaceholders('Hello {{name}}, your age is {{age}}');

        self::assertCount(2, $placeholders);
        self::assertContains('name', $placeholders);
        self::assertContains('age', $placeholders);
    }

    public function testRenderTemplate(): void
    {
        $promptService = self::getService(PromptService::class);

        $result = $promptService->renderTemplate(
            'Hello {{name}}!',
            ['name' => 'World']
        );

        self::assertSame('Hello World!', $result);
    }

    public function testSwitchToVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-switch-version',
            'Version 1',
            null,
            [],
            'test-user',
            'Initial version'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        $promptService->addVersion(
            $promptId,
            'Version 2',
            'Second version',
            'test-user'
        );

        $result = $promptService->switchToVersion($promptId, 1);

        self::assertInstanceOf(PromptVersion::class, $result);
        self::assertSame(3, $result->getVersion()); // 新版本号应该是3（v1,v2已存在）
        self::assertSame('Version 1', $result->getContent()); // 内容应该是从版本1复制的
    }

    public function testUpdatePrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'test-update-prompt',
            'Original content',
            null,
            [],
            'test-user',
            'Original version'
        );

        $promptId = $prompt->getId();
        self::assertNotNull($promptId);

        $updatedVersion = $promptService->updatePrompt(
            $promptId,
            'Updated content',
            'Updated description',
            'test-user'
        );

        self::assertInstanceOf(PromptVersion::class, $updatedVersion);
        self::assertSame('Updated content', $updatedVersion->getContent());
        self::assertSame('Updated description', $updatedVersion->getChangeNote());
    }
}
