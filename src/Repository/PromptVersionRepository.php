<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\PromptManageBundle\Entity\Prompt;
use Tourze\PromptManageBundle\Entity\PromptVersion;

/**
 * @extends ServiceEntityRepository<PromptVersion>
 */
#[AsRepository(entityClass: PromptVersion::class)]
class PromptVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromptVersion::class);
    }

    public function save(PromptVersion $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PromptVersion $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 获取提示词的下一个版本号
     */
    public function getNextVersionNumber(Prompt $prompt): int
    {
        $maxVersion = $this->createQueryBuilder('pv')
            ->select('MAX(pv.version)')
            ->where('pv.prompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (null !== $maxVersion ? (int) $maxVersion : 0) + 1;
    }

    /**
     * 获取指定提示词的指定版本
     */
    public function findByPromptAndVersion(Prompt $prompt, int $version): ?PromptVersion
    {
        /** @var PromptVersion|null */
        return $this->createQueryBuilder('pv')
            ->where('pv.prompt = :prompt')
            ->andWhere('pv.version = :version')
            ->setParameter('prompt', $prompt)
            ->setParameter('version', $version)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 获取提示词的所有版本（按版本号倒序）
     *
     * @return list<PromptVersion>
     */
    public function findByPromptOrderByVersion(Prompt $prompt): array
    {
        /** @var list<PromptVersion> */
        return $this->createQueryBuilder('pv')
            ->where('pv.prompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->orderBy('pv.version', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 获取提示词的最新版本
     */
    public function findLatestVersion(Prompt $prompt): ?PromptVersion
    {
        /** @var PromptVersion|null */
        return $this->createQueryBuilder('pv')
            ->where('pv.prompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->orderBy('pv.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
