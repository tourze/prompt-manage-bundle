<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\PromptManageBundle\Entity\Project;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Repository\ProjectRepository;

/**
 * @internal
 */
#[CoversClass(ProjectRepository::class)]
#[RunTestsInSeparateProcesses]
final class ProjectRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // AbstractRepositoryTestCase 会自动处理设置
    }

    protected function createNewEntity(): object
    {
        $project = new Project();
        $project->setName('Test Project ' . uniqid());
        $project->setDescription('Test Description');

        return $project;
    }

    /**
     * @return ServiceEntityRepository<Project>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(ProjectRepository::class);
    }

    public function testFindWithPromptCounts(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(ProjectRepository::class, $repository);

        // 创建项目
        $project1 = new Project();
        $project1->setName('Project 1 ' . uniqid());
        $repository->save($project1);

        $project2 = new Project();
        $project2->setName('Project 2 ' . uniqid());
        $repository->save($project2);

        // 为第一个项目添加提示词
        $prompt1 = new Prompt();
        $prompt1->setName('Prompt 1 ' . uniqid());
        $prompt1->setProject($project1);
        self::getEntityManager()->persist($prompt1);

        $prompt2 = new Prompt();
        $prompt2->setName('Prompt 2 ' . uniqid());
        $prompt2->setProject($project1);
        self::getEntityManager()->persist($prompt2);

        self::getEntityManager()->flush();

        $results = $repository->findWithPromptCounts();

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(2, count($results));

        // 验证结果结构
        foreach ($results as $project) {
            $this->assertInstanceOf(Project::class, $project);
        }

        // 验证我们创建的项目在结果中
        $createdProjectIds = [$project1->getId(), $project2->getId()];
        $resultProjectIds = array_map(static fn (Project $project) => $project->getId(), $results);

        foreach ($createdProjectIds as $projectId) {
            $this->assertContains($projectId, $resultProjectIds);
        }
    }

    public function testFindByName(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(ProjectRepository::class, $repository);

        $project = new Project();
        $project->setName('Unique Project Name');
        $repository->save($project);

        $found = $repository->findByName('Unique Project Name');
        $this->assertNotNull($found);
        $this->assertEquals('Unique Project Name', $found->getName());

        $notFound = $repository->findByName('Non-existent Project');
        $this->assertNull($notFound);
    }

    public function testFindByNameReturnsCorrectType(): void
    {
        $repository = self::getRepository();
        self::assertInstanceOf(ProjectRepository::class, $repository);

        $project = new Project();
        $project->setName('Type Test Project');
        $repository->save($project);

        $found = $repository->findByName('Type Test Project');
        $this->assertInstanceOf(Project::class, $found);
    }
}
