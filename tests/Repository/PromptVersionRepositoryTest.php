<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;
use Tourze\PromptManageBundle\Repository\PromptRepository;
use Tourze\PromptManageBundle\Repository\PromptVersionRepository;

/**
 * @internal
 */
#[CoversClass(PromptVersionRepository::class)]
#[RunTestsInSeparateProcesses]
final class PromptVersionRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // AbstractRepositoryTestCase 会自动处理设置
    }

    public function testGetNextVersionNumber(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptVersionRepository::class, $repository);
        $promptRepository = self::getService(PromptRepository::class);

        $prompt = new Prompt();
        $prompt->setName('Version Test Prompt');
        $promptRepository->save($prompt, true);

        // 第一个版本应该是 1
        $nextVersion = $repository->getNextVersionNumber($prompt);
        $this->assertEquals(1, $nextVersion);

        // 创建第一个版本
        $version1 = new PromptVersion();
        $version1->setPrompt($prompt);
        $version1->setVersion(1);
        $version1->setContent('First version content');
        $repository->save($version1, true);

        // 下一个版本应该是 2
        $nextVersion = $repository->getNextVersionNumber($prompt);
        $this->assertEquals(2, $nextVersion);

        // 创建第二个版本
        $version2 = new PromptVersion();
        $version2->setPrompt($prompt);
        $version2->setVersion(2);
        $version2->setContent('Second version content');
        $repository->save($version2, true);

        // 下一个版本应该是 3
        $nextVersion = $repository->getNextVersionNumber($prompt);
        $this->assertEquals(3, $nextVersion);
    }

    public function testFindByPromptAndVersion(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptVersionRepository::class, $repository);
        $promptRepository = self::getService(PromptRepository::class);

        $prompt = new Prompt();
        $prompt->setName('Find Version Test');
        $promptRepository->save($prompt, true);

        $version = new PromptVersion();
        $version->setPrompt($prompt);
        $version->setVersion(1);
        $version->setContent('Test content');
        $repository->save($version, true);

        $found = $repository->findByPromptAndVersion($prompt, 1);
        $this->assertNotNull($found);
        $this->assertEquals('Test content', $found->getContent());
        $this->assertEquals(1, $found->getVersion());

        $notFound = $repository->findByPromptAndVersion($prompt, 999);
        $this->assertNull($notFound);
    }

    public function testFindByPromptOrderByVersion(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptVersionRepository::class, $repository);
        $promptRepository = self::getService(PromptRepository::class);

        $prompt = new Prompt();
        $prompt->setName('Order Test Prompt');
        $promptRepository->save($prompt, true);

        // 创建多个版本（乱序）
        $version2 = new PromptVersion();
        $version2->setPrompt($prompt);
        $version2->setVersion(2);
        $version2->setContent('Second version');
        $repository->save($version2, true);

        $version1 = new PromptVersion();
        $version1->setPrompt($prompt);
        $version1->setVersion(1);
        $version1->setContent('First version');
        $repository->save($version1, true);

        $version3 = new PromptVersion();
        $version3->setPrompt($prompt);
        $version3->setVersion(3);
        $version3->setContent('Third version');
        $repository->save($version3, true);

        $versions = $repository->findByPromptOrderByVersion($prompt);

        $this->assertCount(3, $versions);
        $this->assertEquals(3, $versions[0]->getVersion());
        $this->assertEquals(2, $versions[1]->getVersion());
        $this->assertEquals(1, $versions[2]->getVersion());
    }

    public function testFindLatestVersion(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(PromptVersionRepository::class, $repository);
        $promptRepository = self::getService(PromptRepository::class);

        $prompt = new Prompt();
        $prompt->setName('Latest Version Test');
        $promptRepository->save($prompt, true);

        $version1 = new PromptVersion();
        $version1->setPrompt($prompt);
        $version1->setVersion(1);
        $version1->setContent('First version');
        $repository->save($version1, true);

        $version2 = new PromptVersion();
        $version2->setPrompt($prompt);
        $version2->setVersion(2);
        $version2->setContent('Latest version');
        $repository->save($version2, true);

        $latest = $repository->findLatestVersion($prompt);

        $this->assertNotNull($latest);
        $this->assertEquals(2, $latest->getVersion());
        $this->assertEquals('Latest version', $latest->getContent());
    }

    protected function createNewEntity(): object
    {
        $prompt = new Prompt();
        $prompt->setName('Test Prompt ' . uniqid());

        $version = new PromptVersion();
        $version->setPrompt($prompt);
        $version->setVersion(1);
        $version->setContent('Test content ' . uniqid());

        return $version;
    }

    /**
     * @return ServiceEntityRepository<PromptVersion>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(PromptVersionRepository::class);
    }
}
