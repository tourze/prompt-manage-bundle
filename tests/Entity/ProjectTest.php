<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;

/**
 * Project实体测试
 * @internal
 */
#[CoversClass(Project::class)]
final class ProjectTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $project = new Project();

        self::assertNull($project->getId());
        self::assertSame('', $project->getName());
        self::assertNull($project->getDescription());
        self::assertCount(0, $project->getPrompts());
    }

    public function testSettersAndGetters(): void
    {
        $project = new Project();

        $project->setName('Test Project');
        self::assertSame('Test Project', $project->getName());

        $project->setDescription('Test Description');
        self::assertSame('Test Description', $project->getDescription());

        $project->setDescription(null);
        self::assertNull($project->getDescription());
    }

    public function testNameValidation(): void
    {
        $project = new Project();

        // 测试空字符串
        $project->setName('');
        self::assertSame('', $project->getName());

        // 测试长名称（在约束内）
        $longName = str_repeat('A', 50);
        $project->setName($longName);
        self::assertSame($longName, $project->getName());
    }

    public function testDescriptionValidation(): void
    {
        $project = new Project();

        // 测试长描述（在约束内）
        $longDescription = str_repeat('A', 255);
        $project->setDescription($longDescription);
        self::assertSame($longDescription, $project->getDescription());

        // 测试null描述
        $project->setDescription(null);
        self::assertNull($project->getDescription());
    }

    public function testPromptsManagement(): void
    {
        $project = new Project();
        $prompt1 = new Prompt();
        $prompt1->setName('Prompt 1');
        $prompt2 = new Prompt();
        $prompt2->setName('Prompt 2');

        // 测试添加prompt
        $project->addPrompt($prompt1);
        self::assertCount(1, $project->getPrompts());
        self::assertTrue($project->getPrompts()->contains($prompt1));
        self::assertSame($project, $prompt1->getProject());

        // 测试添加第二个prompt
        $project->addPrompt($prompt2);
        self::assertCount(2, $project->getPrompts());
        self::assertTrue($project->getPrompts()->contains($prompt2));
        self::assertSame($project, $prompt2->getProject());

        // 测试重复添加同一个prompt
        $project->addPrompt($prompt1);
        self::assertCount(2, $project->getPrompts());

        // 测试移除prompt
        $project->removePrompt($prompt1);
        self::assertCount(1, $project->getPrompts());
        self::assertFalse($project->getPrompts()->contains($prompt1));
        self::assertNull($prompt1->getProject());

        // 测试移除不存在的prompt
        $project->removePrompt($prompt1);
        self::assertCount(1, $project->getPrompts());
    }

    public function testRemovePromptWithDifferentProject(): void
    {
        $project1 = new Project();
        $project1->setName('Project 1');
        $project2 = new Project();
        $project2->setName('Project 2');
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 将prompt分配给project2
        $project2->addPrompt($prompt);
        self::assertSame($project2, $prompt->getProject());

        // 从project1移除prompt（project1没有这个prompt）
        $project1->removePrompt($prompt);
        // prompt应该仍然属于project2
        self::assertSame($project2, $prompt->getProject());
        self::assertTrue($project2->getPrompts()->contains($prompt));
    }

    public function testToString(): void
    {
        $project = new Project();
        $project->setName('Test Project');

        self::assertSame('Test Project', (string) $project);
    }

    public function testToStringWithEmptyName(): void
    {
        $project = new Project();

        self::assertSame('', (string) $project);
    }

    public function testPromptsCollectionInitialization(): void
    {
        $project = new Project();

        // 验证prompts集合已正确初始化（空集合）
        self::assertCount(0, $project->getPrompts());
    }

    public function testBidirectionalRelationshipConsistency(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 通过project添加prompt
        $project->addPrompt($prompt);
        self::assertSame($project, $prompt->getProject());
        self::assertTrue($project->getPrompts()->contains($prompt));

        // 通过prompt设置project
        $prompt2 = new Prompt();
        $prompt2->setName('Test Prompt 2');
        $prompt2->setProject($project);

        // 验证关系是否正确建立（注意：这里只是单向设置，双向需要手动维护）
        self::assertSame($project, $prompt2->getProject());
    }

    public function testMultiplePromptsWithSameProject(): void
    {
        $project = new Project();
        $project->setName('Shared Project');

        $prompts = [];
        for ($i = 1; $i <= 5; ++$i) {
            $prompt = new Prompt();
            $prompt->setName("Prompt {$i}");
            $project->addPrompt($prompt);
            $prompts[] = $prompt;
        }

        self::assertCount(5, $project->getPrompts());

        foreach ($prompts as $prompt) {
            self::assertSame($project, $prompt->getProject());
            self::assertTrue($project->getPrompts()->contains($prompt));
        }

        // 移除中间的prompt
        $project->removePrompt($prompts[2]);
        self::assertCount(4, $project->getPrompts());
        self::assertNull($prompts[2]->getProject());
        self::assertFalse($project->getPrompts()->contains($prompts[2]));

        // 其他prompt应该不受影响
        unset($prompts[2]);
        foreach ($prompts as $prompt) {
            self::assertSame($project, $prompt->getProject());
            self::assertTrue($project->getPrompts()->contains($prompt));
        }
    }

    protected function createEntity(): object
    {
        return new Project();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['name', 'Test Project'],
            ['description', 'Test Description'],
        ];
    }
}
