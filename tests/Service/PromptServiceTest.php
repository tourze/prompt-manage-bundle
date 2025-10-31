<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Service\PromptService;

/**
 * @internal
 */
#[CoversClass(PromptService::class)]
#[RunTestsInSeparateProcesses]
final class PromptServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // AbstractIntegrationTestCase 会自动处理设置
    }

    public function testCreatePrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'Test Prompt',
            'Hello {name}!',
            'Test Project',
            ['test', 'example'],
            '1',
            'Initial version'
        );

        $this->assertNotNull($prompt->getId());
        $this->assertEquals('Test Prompt', $prompt->getName());
        $this->assertEquals(1, $prompt->getCurrentVersion());
        $this->assertNotNull($prompt->getProject());
        $this->assertEquals('Test Project', $prompt->getProject()->getName());
        $this->assertCount(2, $prompt->getTags());

        // 验证初始版本已创建
        // 刷新实体以确保关联被正确加载
        self::getEntityManager()->refresh($prompt);
        $content = $prompt->getCurrentVersionContent();
        $this->assertEquals('Hello {name}!', $content);
    }

    public function testCreatePromptWithDuplicateName(): void
    {
        $promptService = self::getService(PromptService::class);

        $promptService->createPrompt('Duplicate Test', 'Content 1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('提示词名称 "Duplicate Test" 已存在');

        $promptService->createPrompt('Duplicate Test', 'Content 2');
    }

    public function testUpdatePrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'Update Test',
            'Original content',
            null,
            [],
            '1',
            'Initial version'
        );

        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        $version = $promptService->updatePrompt(
            $promptId,
            'Updated content',
            'Updated for testing',
            '2'
        );

        $this->assertEquals(2, $version->getVersion());
        $this->assertEquals('Updated content', $version->getContent());
        $this->assertEquals('Updated for testing', $version->getChangeNote());
        $this->assertEquals('2', $version->getCreatedBy());

        // 验证提示词的当前版本已更新
        self::getEntityManager()->refresh($prompt);
        $this->assertEquals(2, $prompt->getCurrentVersion());
    }

    public function testSwitchToVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        $prompt = $promptService->createPrompt(
            'Switch Test',
            'Version 1 content',
            null,
            [],
            '1',
            'Initial version'
        );

        // 创建第二个版本
        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        $promptService->updatePrompt(
            $promptId,
            'Version 2 content',
            'Second version',
            '1'
        );

        // 切换回第一个版本
        $newVersion = $promptService->switchToVersion(
            $promptId,
            1,
            '3'
        );

        $this->assertEquals(3, $newVersion->getVersion());
        $this->assertEquals('Version 1 content', $newVersion->getContent());
        $changeNote = $newVersion->getChangeNote();
        $this->assertNotNull($changeNote);
        $this->assertStringContainsString('切换到版本 v1', $changeNote);
        $this->assertEquals('3', $newVersion->getCreatedBy());

        // 验证当前版本已更新
        self::getEntityManager()->refresh($prompt);
        $this->assertEquals(3, $prompt->getCurrentVersion());
    }

    public function testGetPromptContent(): void
    {
        $promptService = self::getService(PromptService::class);
        $em = self::getEntityManager();

        $prompt = $promptService->createPrompt(
            'Content Test',
            'Test content for retrieval'
        );

        // 刷新实体以确保关联被正确加载
        $em->refresh($prompt);

        $content = $promptService->getPromptContent('Content Test');
        $this->assertEquals('Test content for retrieval', $content);

        $notFound = $promptService->getPromptContent('Non Existent');
        $this->assertNull($notFound);
    }

    public function testExtractPlaceholders(): void
    {
        $promptService = self::getService(PromptService::class);

        $content = 'Hello {name}! Your order {order_id} is ready. {greeting} {name}';

        $placeholders = $promptService->extractPlaceholders($content);

        $this->assertContains('name', $placeholders);
        $this->assertContains('order_id', $placeholders);
        $this->assertContains('greeting', $placeholders);
        $this->assertCount(3, $placeholders); // 去重后应该只有3个
    }

    public function testRenderTemplate(): void
    {
        $promptService = self::getService(PromptService::class);

        $template = 'Hello {name}! Your balance is {balance}.';
        $params = [
            'name' => 'John',
            'balance' => '$100',
        ];

        $rendered = $promptService->renderTemplate($template, $params);

        $this->assertEquals('Hello John! Your balance is $100.', $rendered);
    }

    public function testDeletePrompt(): void
    {
        $promptService = self::getService(PromptService::class);
        $em = self::getEntityManager();

        $prompt = $promptService->createPrompt('Delete Test', 'Content');
        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        // 验证提示词存在
        $this->assertNotNull($em->find(Prompt::class, $promptId));

        $promptService->deletePrompt($promptId, '1');

        // 验证提示词已从数据库中完全移除（硬删除）
        $this->assertNull($em->find(Prompt::class, $promptId));
    }

    public function testDeleteNonExistentPrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('提示词不存在');

        $promptService->deletePrompt(999999);
    }

    public function testAddVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        // 先创建一个提示词
        $prompt = $promptService->createPrompt('Add Version Test', 'Original content');
        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        // 添加新版本
        $version = $promptService->addVersion($promptId, 'New content', 'Added new version', 'user1');

        $this->assertEquals(2, $version->getVersion());
        $this->assertEquals('New content', $version->getContent());
        $this->assertEquals('Added new version', $version->getChangeNote());
        $this->assertEquals('user1', $version->getCreatedBy());
    }

    public function testCreateVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        // 先创建一个提示词
        $prompt = $promptService->createPrompt('Create Version Test', 'Original content');
        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        // 创建新版本
        $version = $promptService->createVersion($promptId, 'New content', 'Created new version', 'user1');

        $this->assertEquals(2, $version->getVersion());
        $this->assertEquals('New content', $version->getContent());
    }

    public function testUpdatePromptWithNonExistentPrompt(): void
    {
        $promptService = self::getService(PromptService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('提示词不存在');

        $promptService->updatePrompt(999999, 'content', 'note');
    }

    public function testSwitchToNonExistentVersion(): void
    {
        $promptService = self::getService(PromptService::class);

        // 先创建一个提示词
        $prompt = $promptService->createPrompt('Switch Version Test', 'Original content');
        $promptId = $prompt->getId();
        $this->assertNotNull($promptId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('版本 v999 不存在');

        $promptService->switchToVersion($promptId, 999);
    }
}
