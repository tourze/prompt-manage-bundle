<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\PromptManageBundle\Entity\Project;

/**
 * @extends ServiceEntityRepository<Project>
 */
#[AsRepository(entityClass: Project::class)]
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Project $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找项目及其提示词数量
     *
     * @return list<Project>
     */
    public function findWithPromptCounts(): array
    {
        /** @var list<Project> */
        return $this->createQueryBuilder('p')
            ->leftJoin('p.prompts', 'pr')
            ->groupBy('p.id')
            ->orderBy('p.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据名称查找项目
     */
    public function findByName(string $name): ?Project
    {
        /** @var Project|null */
        return $this->createQueryBuilder('p')
            ->where('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
