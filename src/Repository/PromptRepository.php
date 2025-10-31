<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\PromptManageBundle\Entity\Prompt;

/**
 * @extends ServiceEntityRepository<Prompt>
 */
#[AsRepository(entityClass: Prompt::class)]
class PromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prompt::class);
    }

    public function save(Prompt $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Prompt $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找所有提示词
     *
     * @return list<Prompt>
     */
    public function findAll(): array
    {
        /** @var list<Prompt> */
        return $this->createQueryBuilder('p')
            ->orderBy('p.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 根据名称查找提示词
     */
    public function findByName(string $name): ?Prompt
    {
        /** @var Prompt|null */
        return $this->createQueryBuilder('p')
            ->where('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据项目查找提示词
     *
     * @return list<Prompt>
     */
    public function findByProject(int $projectId): array
    {
        /** @var list<Prompt> */
        return $this->createQueryBuilder('p')
            ->where('p.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('p.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 搜索提示词（根据名称或内容）
     *
     * @return list<Prompt>
     */
    public function search(string $keyword): array
    {
        /** @var list<Prompt> */
        return $this->createQueryBuilder('p')
            ->leftJoin('p.versions', 'pv')
            ->where('p.name LIKE :keyword OR pv.content LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('p.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
