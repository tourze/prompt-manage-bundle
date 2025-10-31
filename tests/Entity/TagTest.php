<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\Tag;

/**
 * Tag实体测试
 * @internal
 */
#[CoversClass(Tag::class)]
final class TagTest extends AbstractEntityTestCase
{
    public function testConstruct(): void
    {
        $tag = new Tag();

        self::assertNull($tag->getId());
        self::assertSame('', $tag->getName());
        self::assertCount(0, $tag->getPrompts());
    }

    public function testSettersAndGetters(): void
    {
        $tag = new Tag();

        $tag->setName('Test Tag');
        self::assertSame('Test Tag', $tag->getName());
    }

    public function testNameValidation(): void
    {
        $tag = new Tag();

        // 测试空字符串
        $tag->setName('');
        self::assertSame('', $tag->getName());

        // 测试长名称（在约束内）
        $longName = str_repeat('A', 30);
        $tag->setName($longName);
        self::assertSame($longName, $tag->getName());
    }

    public function testPromptsManagement(): void
    {
        $tag = new Tag();
        $tag->setName('Test Tag');

        $prompt1 = new Prompt();
        $prompt1->setName('Prompt 1');
        $prompt2 = new Prompt();
        $prompt2->setName('Prompt 2');

        // 测试添加prompt
        $tag->addPrompt($prompt1);
        self::assertCount(1, $tag->getPrompts());
        self::assertTrue($tag->getPrompts()->contains($prompt1));
        // 验证双向关系：prompt应该也包含这个tag
        self::assertTrue($prompt1->getTags()->contains($tag));

        // 测试添加第二个prompt
        $tag->addPrompt($prompt2);
        self::assertCount(2, $tag->getPrompts());
        self::assertTrue($tag->getPrompts()->contains($prompt2));
        self::assertTrue($prompt2->getTags()->contains($tag));

        // 测试重复添加同一个prompt
        $tag->addPrompt($prompt1);
        self::assertCount(2, $tag->getPrompts());

        // 测试移除prompt
        $tag->removePrompt($prompt1);
        self::assertCount(1, $tag->getPrompts());
        self::assertFalse($tag->getPrompts()->contains($prompt1));
        // 验证双向关系：prompt应该也不包含这个tag
        self::assertFalse($prompt1->getTags()->contains($tag));

        // 测试移除不存在的prompt
        $tag->removePrompt($prompt1);
        self::assertCount(1, $tag->getPrompts());
    }

    public function testManyToManyRelationshipBidirectional(): void
    {
        $tag1 = new Tag();
        $tag1->setName('AI');
        $tag2 = new Tag();
        $tag2->setName('Chat');

        $prompt = new Prompt();
        $prompt->setName('Chat Prompt');

        // 从tag方向建立关系
        $tag1->addPrompt($prompt);
        self::assertTrue($tag1->getPrompts()->contains($prompt));
        self::assertTrue($prompt->getTags()->contains($tag1));

        // 从tag方向建立关系（正确维护双向关系）
        $tag2->addPrompt($prompt);
        self::assertTrue($tag2->getPrompts()->contains($prompt));
        self::assertTrue($prompt->getTags()->contains($tag2));

        // 验证prompt包含两个tag
        self::assertCount(2, $prompt->getTags());
        self::assertTrue($prompt->getTags()->contains($tag1));
        self::assertTrue($prompt->getTags()->contains($tag2));

        // 验证tag1和tag2都包含prompt
        self::assertCount(1, $tag1->getPrompts());
        self::assertCount(1, $tag2->getPrompts());
        self::assertTrue($tag1->getPrompts()->contains($prompt));
        self::assertTrue($tag2->getPrompts()->contains($prompt));
    }

    public function testRemovePromptRelationshipConsistency(): void
    {
        $tag = new Tag();
        $tag->setName('Test Tag');
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 建立关系
        $tag->addPrompt($prompt);
        self::assertTrue($tag->getPrompts()->contains($prompt));
        self::assertTrue($prompt->getTags()->contains($tag));

        // 从tag方向移除关系
        $tag->removePrompt($prompt);
        self::assertFalse($tag->getPrompts()->contains($prompt));
        self::assertFalse($prompt->getTags()->contains($tag));
    }

    public function testMultiplePromptsWithSameTag(): void
    {
        $tag = new Tag();
        $tag->setName('Popular Tag');

        $prompts = [];
        for ($i = 1; $i <= 5; ++$i) {
            $prompt = new Prompt();
            $prompt->setName("Prompt {$i}");
            $tag->addPrompt($prompt);
            $prompts[] = $prompt;
        }

        self::assertCount(5, $tag->getPrompts());

        foreach ($prompts as $prompt) {
            self::assertTrue($tag->getPrompts()->contains($prompt));
            self::assertTrue($prompt->getTags()->contains($tag));
        }

        // 移除中间的prompt
        $tag->removePrompt($prompts[2]);
        self::assertCount(4, $tag->getPrompts());
        self::assertFalse($tag->getPrompts()->contains($prompts[2]));
        self::assertFalse($prompts[2]->getTags()->contains($tag));

        // 其他prompt应该不受影响
        unset($prompts[2]);
        foreach ($prompts as $prompt) {
            self::assertTrue($tag->getPrompts()->contains($prompt));
            self::assertTrue($prompt->getTags()->contains($tag));
        }
    }

    public function testComplexManyToManyScenario(): void
    {
        // 创建多个tag和prompt，测试复杂的多对多关系
        $tags = [];
        $prompts = [];

        // 创建3个tag
        for ($i = 1; $i <= 3; ++$i) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $tags[] = $tag;
        }

        // 创建3个prompt
        for ($i = 1; $i <= 3; ++$i) {
            $prompt = new Prompt();
            $prompt->setName("Prompt {$i}");
            $prompts[] = $prompt;
        }

        // 建立复杂关系：
        // Prompt 1 -> Tag 1, Tag 2
        // Prompt 2 -> Tag 2, Tag 3
        // Prompt 3 -> Tag 1, Tag 3
        $tags[0]->addPrompt($prompts[0]); // Tag1 -> Prompt1
        $tags[1]->addPrompt($prompts[0]); // Tag2 -> Prompt1
        $tags[1]->addPrompt($prompts[1]); // Tag2 -> Prompt2
        $tags[2]->addPrompt($prompts[1]); // Tag3 -> Prompt2
        $tags[0]->addPrompt($prompts[2]); // Tag1 -> Prompt3
        $tags[2]->addPrompt($prompts[2]); // Tag3 -> Prompt3

        // 验证Tag1的关系
        self::assertCount(2, $tags[0]->getPrompts()); // Prompt1, Prompt3
        self::assertTrue($tags[0]->getPrompts()->contains($prompts[0]));
        self::assertTrue($tags[0]->getPrompts()->contains($prompts[2]));

        // 验证Tag2的关系
        self::assertCount(2, $tags[1]->getPrompts()); // Prompt1, Prompt2
        self::assertTrue($tags[1]->getPrompts()->contains($prompts[0]));
        self::assertTrue($tags[1]->getPrompts()->contains($prompts[1]));

        // 验证Tag3的关系
        self::assertCount(2, $tags[2]->getPrompts()); // Prompt2, Prompt3
        self::assertTrue($tags[2]->getPrompts()->contains($prompts[1]));
        self::assertTrue($tags[2]->getPrompts()->contains($prompts[2]));

        // 验证Prompt1的关系
        self::assertCount(2, $prompts[0]->getTags()); // Tag1, Tag2
        self::assertTrue($prompts[0]->getTags()->contains($tags[0]));
        self::assertTrue($prompts[0]->getTags()->contains($tags[1]));

        // 验证Prompt2的关系
        self::assertCount(2, $prompts[1]->getTags()); // Tag2, Tag3
        self::assertTrue($prompts[1]->getTags()->contains($tags[1]));
        self::assertTrue($prompts[1]->getTags()->contains($tags[2]));

        // 验证Prompt3的关系
        self::assertCount(2, $prompts[2]->getTags()); // Tag1, Tag3
        self::assertTrue($prompts[2]->getTags()->contains($tags[0]));
        self::assertTrue($prompts[2]->getTags()->contains($tags[2]));
    }

    public function testToString(): void
    {
        $tag = new Tag();
        $tag->setName('Test Tag');

        self::assertSame('Test Tag', (string) $tag);
    }

    public function testToStringWithEmptyName(): void
    {
        $tag = new Tag();

        self::assertSame('', (string) $tag);
    }

    public function testPromptsCollectionInitialization(): void
    {
        $tag = new Tag();

        // 验证prompts集合已正确初始化（空集合）
        self::assertCount(0, $tag->getPrompts());
    }

    public function testPromptAddTagBehavior(): void
    {
        $tag = new Tag();
        $tag->setName('Test Tag');
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 从prompt方向添加tag（当前实现只维护单向关系）
        $prompt->addTag($tag);

        // 验证prompt的tags集合包含tag
        self::assertTrue($prompt->getTags()->contains($tag));

        // 但tag的prompts集合不包含prompt（因为没有维护反向关系）
        self::assertFalse($tag->getPrompts()->contains($prompt));

        // 这说明当前实现只维护了单向关系
        self::assertCount(1, $prompt->getTags());
        self::assertCount(0, $tag->getPrompts());
    }

    public function testAddingPromptWithExistingTags(): void
    {
        $tag1 = new Tag();
        $tag1->setName('Existing Tag');
        $tag2 = new Tag();
        $tag2->setName('New Tag');

        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        // 从tag1方向建立关系（正确维护双向关系）
        $tag1->addPrompt($prompt);
        self::assertTrue($tag1->getPrompts()->contains($prompt));
        self::assertTrue($prompt->getTags()->contains($tag1));

        // 从tag2方向添加这个prompt
        $tag2->addPrompt($prompt);
        self::assertTrue($tag2->getPrompts()->contains($prompt));
        self::assertTrue($prompt->getTags()->contains($tag2));

        // 验证prompt现在有两个tag
        self::assertCount(2, $prompt->getTags());
        self::assertCount(1, $tag1->getPrompts());
        self::assertCount(1, $tag2->getPrompts());
    }

    public function testTagNameConstraints(): void
    {
        $tag = new Tag();

        // 测试各种有效的tag名称
        $validNames = [
            'AI',
            'machine-learning',
            'nlp_processing',
            'ChatGPT',
            '123',
            'tag with spaces',
            'специальный', // 非英文字符
            '中文标签',
        ];

        foreach ($validNames as $name) {
            $tag->setName($name);
            self::assertSame($name, $tag->getName());
        }
    }

    public function testEmptyTag(): void
    {
        $tag = new Tag();

        // 测试空tag的行为
        self::assertSame('', $tag->getName());
        self::assertCount(0, $tag->getPrompts());
        self::assertSame('', (string) $tag);

        // 空tag也应该能够添加prompt
        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $tag->addPrompt($prompt);
        self::assertCount(1, $tag->getPrompts());
        self::assertTrue($tag->getPrompts()->contains($prompt));
    }

    protected function createEntity(): object
    {
        return new Tag();
    }

    /**
     * @return array<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['name', 'Test Tag'],
        ];
    }
}
