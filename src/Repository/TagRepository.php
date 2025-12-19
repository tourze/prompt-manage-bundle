<?php

declare(strict_types=1);

namespace Tourze\PromptManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\PromptManageBundle\Entity\Tag;

/**
 * @extends ServiceEntityRepository<Tag>
 */
#[AsRepository(entityClass: Tag::class)]
final class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function save(Tag $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tag $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据名称查找标签
     */
    public function findByName(string $name): ?Tag
    {
        /** @var Tag|null */
        return $this->createQueryBuilder('t')
            ->where('t.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * 根据名称数组查找或创建标签
     *
     * @param list<string> $names
     * @return list<Tag>
     */
    public function findOrCreateByNames(array $names): array
    {
        if ([] === $names) {
            return [];
        }

        /** @var list<Tag> $existingTags */
        $existingTags = $this->createQueryBuilder('t')
            ->where('t.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult()
        ;

        /** @var list<string> $existingNames */
        $existingNames = array_map(static fn (Tag $tag): string => $tag->getName(), $existingTags);
        $missingNames = array_diff($names, $existingNames);

        $newTags = [];
        foreach ($missingNames as $name) {
            $tag = new Tag();
            $tag->setName($name);
            $this->save($tag);
            $newTags[] = $tag;
        }

        return [...$existingTags, ...$newTags];
    }

    /**
     * 获取最常用的标签
     *
     * @return list<Tag>
     */
    public function findMostUsed(int $limit = 10): array
    {
        /** @var list<Tag> */
        return $this->createQueryBuilder('t')
            ->leftJoin('t.prompts', 'p')
            ->groupBy('t.id')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
