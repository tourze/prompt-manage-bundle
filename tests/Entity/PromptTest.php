<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Entity\Tag;

/**
 * Prompt实体测试
 * @internal
 */
#[CoversClass(Prompt::class)]
final class PromptTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $prompt = new Prompt();

        self::assertNull($prompt->getId());
        self::assertSame('', $prompt->getName());
        self::assertNull($prompt->getProject());
        self::assertSame(1, $prompt->getCurrentVersion());
        self::assertCount(0, $prompt->getVersions());
        self::assertCount(0, $prompt->getTags());
    }

    public function testSettersAndGetters(): void
    {
        $prompt = new Prompt();

        $prompt->setName('Test Prompt');
        self::assertSame('Test Prompt', $prompt->getName());

        $prompt->setCurrentVersion(2);
        self::assertSame(2, $prompt->getCurrentVersion());
    }

    public function testNameValidation(): void
    {
        $prompt = new Prompt();

        // 测试空字符串
        $prompt->setName('');
        self::assertSame('', $prompt->getName());

        // 测试长名称（在约束内）
        $longName = str_repeat('A', 100);
        $prompt->setName($longName);
        self::assertSame($longName, $prompt->getName());
    }

    public function testCurrentVersionValidation(): void
    {
        $prompt = new Prompt();

        // 测试正数
        $prompt->setCurrentVersion(5);
        self::assertSame(5, $prompt->getCurrentVersion());

        // 测试0（应该是有效的）
        $prompt->setCurrentVersion(0);
        self::assertSame(0, $prompt->getCurrentVersion());
    }

    public function testProjectRelationship(): void
    {
        $prompt = new Prompt();
        $project = new Project();
        $project->setName('Test Project');

        $prompt->setProject($project);
        self::assertSame($project, $prompt->getProject());

        $prompt->setProject(null);
        self::assertNull($prompt->getProject());
    }

    public function testVersionsManagement(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $version1 = new PromptVersion();
        $version1->setVersion(1);
        $version1->setContent('Content v1');

        $version2 = new PromptVersion();
        $version2->setVersion(2);
        $version2->setContent('Content v2');

        // 测试添加版本
        $prompt->addVersion($version1);
        self::assertCount(1, $prompt->getVersions());
        self::assertTrue($prompt->getVersions()->contains($version1));
        self::assertSame($prompt, $version1->getPrompt());

        // 测试添加第二个版本
        $prompt->addVersion($version2);
        self::assertCount(2, $prompt->getVersions());
        self::assertTrue($prompt->getVersions()->contains($version2));
        self::assertSame($prompt, $version2->getPrompt());

        // 测试重复添加同一个版本
        $prompt->addVersion($version1);
        self::assertCount(2, $prompt->getVersions());

        // 测试移除版本
        $prompt->removeVersion($version1);
        self::assertCount(1, $prompt->getVersions());
        self::assertFalse($prompt->getVersions()->contains($version1));
        self::assertNull($version1->getPrompt());

        // 测试移除不存在的版本
        $prompt->removeVersion($version1);
        self::assertCount(1, $prompt->getVersions());
    }

    public function testRemoveVersionWithDifferentPrompt(): void
    {
        $prompt1 = new Prompt();
        $prompt1->setName('Prompt 1');
        $prompt2 = new Prompt();
        $prompt2->setName('Prompt 2');

        $version = new PromptVersion();
        $version->setVersion(1);
        $version->setContent('Test Content');

        // 将版本分配给prompt2
        $prompt2->addVersion($version);
        self::assertSame($prompt2, $version->getPrompt());

        // 从prompt1移除版本（prompt1没有这个版本）
        $prompt1->removeVersion($version);
        // 版本应该仍然属于prompt2
        self::assertSame($prompt2, $version->getPrompt());
        self::assertTrue($prompt2->getVersions()->contains($version));
    }

    public function testTagsManagement(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $tag1 = new Tag();
        $tag1->setName('tag1');

        $tag2 = new Tag();
        $tag2->setName('tag2');

        // 测试添加标签
        $prompt->addTag($tag1);
        self::assertCount(1, $prompt->getTags());
        self::assertTrue($prompt->getTags()->contains($tag1));

        // 测试添加第二个标签
        $prompt->addTag($tag2);
        self::assertCount(2, $prompt->getTags());
        self::assertTrue($prompt->getTags()->contains($tag2));

        // 测试重复添加同一个标签
        $prompt->addTag($tag1);
        self::assertCount(2, $prompt->getTags());

        // 测试移除标签
        $prompt->removeTag($tag1);
        self::assertCount(1, $prompt->getTags());
        self::assertFalse($prompt->getTags()->contains($tag1));

        // 测试移除不存在的标签
        $prompt->removeTag($tag1);
        self::assertCount(1, $prompt->getTags());
    }

    public function testGetCurrentVersionContent(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');
        $prompt->setCurrentVersion(2);

        // 没有版本时返回null
        self::assertNull($prompt->getCurrentVersionContent());

        // 添加版本1
        $version1 = new PromptVersion();
        $version1->setVersion(1);
        $version1->setContent('Content v1');
        $prompt->addVersion($version1);

        // 当前版本是2，但没有版本2，应该返回null
        self::assertNull($prompt->getCurrentVersionContent());

        // 添加版本2
        $version2 = new PromptVersion();
        $version2->setVersion(2);
        $version2->setContent('Content v2');
        $prompt->addVersion($version2);

        // 现在应该返回版本2的内容
        self::assertSame('Content v2', $prompt->getCurrentVersionContent());

        // 改变当前版本为1
        $prompt->setCurrentVersion(1);
        self::assertSame('Content v1', $prompt->getCurrentVersionContent());

        // 设置不存在的版本号
        $prompt->setCurrentVersion(99);
        self::assertNull($prompt->getCurrentVersionContent());
    }

    public function testGetCurrentVersionContentWithMultipleVersions(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 添加多个版本
        for ($i = 1; $i <= 5; ++$i) {
            $version = new PromptVersion();
            $version->setVersion($i);
            $version->setContent("Content v{$i}");
            $prompt->addVersion($version);
        }

        // 测试不同版本的内容
        for ($i = 1; $i <= 5; ++$i) {
            $prompt->setCurrentVersion($i);
            self::assertSame("Content v{$i}", $prompt->getCurrentVersionContent());
        }
    }

    public function testToString(): void
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        self::assertSame('Test Prompt', (string) $prompt);
    }

    public function testToStringWithEmptyName(): void
    {
        $prompt = new Prompt();

        self::assertSame('', (string) $prompt);
    }

    public function testCollectionsInitialization(): void
    {
        $prompt = new Prompt();

        // 验证集合已正确初始化（空集合）
        self::assertCount(0, $prompt->getVersions());
        self::assertCount(0, $prompt->getTags());
    }

    public function testComplexRelationshipScenario(): void
    {
        // 创建一个完整的关系场景
        $project = new Project();
        $project->setName('AI Project');

        $prompt = new Prompt();
        $prompt->setName('Chat Prompt');
        $prompt->setProject($project);
        $prompt->setCurrentVersion(2);

        $tag1 = new Tag();
        $tag1->setName('chat');
        $tag2 = new Tag();
        $tag2->setName('ai');

        $version1 = new PromptVersion();
        $version1->setVersion(1);
        $version1->setContent('Hello {{name}}');
        $version1->setChangeNote('Initial version');

        $version2 = new PromptVersion();
        $version2->setVersion(2);
        $version2->setContent('Hello {{name}}, how can I help you?');
        $version2->setChangeNote('Added help offer');

        // 建立关系
        $prompt->addTag($tag1);
        $prompt->addTag($tag2);
        $prompt->addVersion($version1);
        $prompt->addVersion($version2);
        $project->addPrompt($prompt);

        // 验证所有关系
        self::assertSame($project, $prompt->getProject());
        self::assertTrue($project->getPrompts()->contains($prompt));
        self::assertCount(2, $prompt->getTags());
        self::assertCount(2, $prompt->getVersions());
        self::assertSame('Hello {{name}}, how can I help you?', $prompt->getCurrentVersionContent());
    }

    public function testEmptyVersionsCollection(): void
    {
        $prompt = new Prompt();
        $prompt->setCurrentVersion(1);

        // 空版本集合时应该返回null
        self::assertNull($prompt->getCurrentVersionContent());

        // 确保不会抛出异常
        self::assertCount(0, $prompt->getVersions());
    }

    protected function createEntity(): object
    {
        return new Prompt();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['name', 'Test Prompt'],
            ['currentVersion', 1],
        ];
    }
}
