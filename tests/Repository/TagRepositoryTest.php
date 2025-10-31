<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\Tag;
use Tourze\PromptManageBundle\Repository\TagRepository;

/**
 * @internal
 */
#[CoversClass(TagRepository::class)]
#[RunTestsInSeparateProcesses]
final class TagRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // AbstractRepositoryTestCase 会自动处理设置
    }

    protected function createNewEntity(): object
    {
        $tag = new Tag();
        $tag->setName('Test Tag ' . uniqid());

        return $tag;
    }

    /**
     * @return ServiceEntityRepository<Tag>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(TagRepository::class);
    }

    public function testFindByName(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        $tag = new Tag();
        $tag->setName('Unique Tag Name');
        $repository->save($tag);

        $found = $repository->findByName('Unique Tag Name');
        $this->assertNotNull($found);
        $this->assertEquals('Unique Tag Name', $found->getName());

        $notFound = $repository->findByName('Non-existent Tag');
        $this->assertNull($notFound);
    }

    public function testFindByNameReturnsCorrectType(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        $tag = new Tag();
        $tag->setName('Type Test Tag');
        $repository->save($tag);

        $found = $repository->findByName('Type Test Tag');
        $this->assertInstanceOf(Tag::class, $found);
    }

    public function testFindOrCreateByNamesWithEmptyArray(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        $result = $repository->findOrCreateByNames([]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindOrCreateByNamesWithExistingTags(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        // 创建现有标签
        $existingTag = new Tag();
        $existingTag->setName('Existing Tag');
        $repository->save($existingTag);

        $result = $repository->findOrCreateByNames(['Existing Tag', 'New Tag']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $tagNames = array_map(static fn (Tag $tag): string => $tag->getName(), $result);
        $this->assertContains('Existing Tag', $tagNames);
        $this->assertContains('New Tag', $tagNames);

        foreach ($result as $tag) {
            $this->assertInstanceOf(Tag::class, $tag);
            $this->assertNotNull($tag->getId());
        }
    }

    public function testFindOrCreateByNamesWithAllNewTags(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        $result = $repository->findOrCreateByNames(['New Tag 1', 'New Tag 2']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        foreach ($result as $tag) {
            $this->assertInstanceOf(Tag::class, $tag);
            $this->assertNotNull($tag->getId());
        }

        $tagNames = array_map(static fn (Tag $tag): string => $tag->getName(), $result);
        $this->assertContains('New Tag 1', $tagNames);
        $this->assertContains('New Tag 2', $tagNames);
    }

    public function testFindMostUsedReturnsList(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        $result = $repository->findMostUsed();
        $this->assertIsArray($result);

        foreach ($result as $tag) {
            $this->assertInstanceOf(Tag::class, $tag);
        }
    }

    public function testFindMostUsedWithTags(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        // 创建标签
        $tag1 = new Tag();
        $tag1->setName('Popular Tag');
        $repository->save($tag1);

        $tag2 = new Tag();
        $tag2->setName('Less Popular Tag');
        $repository->save($tag2);

        // 创建提示词并关联标签
        $prompt1 = new Prompt();
        $prompt1->setName('Prompt 1');
        $prompt1->addTag($tag1);
        self::getEntityManager()->persist($prompt1);

        $prompt2 = new Prompt();
        $prompt2->setName('Prompt 2');
        $prompt2->addTag($tag1);
        $prompt2->addTag($tag2);
        self::getEntityManager()->persist($prompt2);

        self::getEntityManager()->flush();

        $result = $repository->findMostUsed(5);

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));

        foreach ($result as $tag) {
            $this->assertInstanceOf(Tag::class, $tag);
        }
    }

    public function testFindMostUsedWithLimit(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(TagRepository::class, $repository);

        // 创建多个标签
        for ($i = 1; $i <= 15; ++$i) {
            $tag = new Tag();
            $tag->setName("Tag {$i}");
            $repository->save($tag);
        }

        $result = $repository->findMostUsed(10);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(10, count($result));
    }
}
