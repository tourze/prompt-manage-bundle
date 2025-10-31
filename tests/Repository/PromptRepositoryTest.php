<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Repository\PromptRepository;

/**
 * @internal
 */
#[CoversClass(PromptRepository::class)]
#[RunTestsInSeparateProcesses]
final class PromptRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // AbstractRepositoryTestCase 会自动处理设置
    }

    public function testSave(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        $prompt = new Prompt();
        $prompt->setName('Test Prompt');

        $repository->save($prompt);

        $this->assertNotNull($prompt->getId());
        $this->assertEquals('Test Prompt', $prompt->getName());
    }

    public function testRemove(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        $prompt = new Prompt();
        $prompt->setName('Test Prompt for Delete');
        $repository->save($prompt);

        $id = $prompt->getId();
        $this->assertNotNull($id);

        $repository->remove($prompt);

        // 验证实体已被硬删除
        $deletedPrompt = $repository->find($id);
        $this->assertNull($deletedPrompt);
    }

    public function testFindAll(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        // 创建两个提示词
        $prompt1 = new Prompt();
        $prompt1->setName('First Prompt');
        $repository->save($prompt1);

        $prompt2 = new Prompt();
        $prompt2->setName('Second Prompt');
        $repository->save($prompt2);

        $result = $repository->findAll();

        // 结果中应该包含两个提示词
        $firstFound = false;
        $secondFound = false;

        foreach ($result as $prompt) {
            if ('First Prompt' === $prompt->getName()) {
                $firstFound = true;
            }
            if ('Second Prompt' === $prompt->getName()) {
                $secondFound = true;
            }
        }

        $this->assertTrue($firstFound, 'First prompt should be found');
        $this->assertTrue($secondFound, 'Second prompt should be found');
    }

    public function testFindByName(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        $prompt = new Prompt();
        $prompt->setName('Unique Prompt Name');
        $repository->save($prompt);

        $found = $repository->findByName('Unique Prompt Name');
        $this->assertNotNull($found);
        $this->assertEquals('Unique Prompt Name', $found->getName());

        // 硬删除后应该找不到
        $repository->remove($prompt);
        $notFound = $repository->findByName('Unique Prompt Name');
        $this->assertNull($notFound);
    }

    public function testSearch(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        $prompt1 = new Prompt();
        $prompt1->setName('Search Test One');
        $repository->save($prompt1);

        $prompt2 = new Prompt();
        $prompt2->setName('Another Prompt');
        $repository->save($prompt2);

        $results = $repository->search('Search');

        $foundNames = array_map(fn ($p) => $p->getName(), $results);
        $this->assertContains('Search Test One', $foundNames);
        $this->assertNotContains('Another Prompt', $foundNames);
    }

    public function testFindByProject(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptRepository::class, $repository);

        // 测试查找不存在的项目ID
        $nonExistentProjectId = 999999;
        $results = $repository->findByProject($nonExistentProjectId);

        // 由于项目ID不存在，结果应该为空
        $this->assertEmpty($results);

        // 验证返回值是数组类型
        $this->assertIsArray($results);
    }

    protected function createNewEntity(): object
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt ' . uniqid());

        return $prompt;
    }

    /**
     * @return ServiceEntityRepository<Prompt>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(PromptRepository::class);
    }
}
