<?php

namespace App\Repository;

use App\Entity\Tags;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tags>
 *
 * @method Tags|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tags|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tags[]    findAll()
 * @method Tags[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tags::class);
    }

    public function add(Tags $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tags $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t')
            ->orderBy('t.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findByArray($tagArray)
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.id in (:tagArray)')
            ->setParameter('tagArray', $tagArray)
            ->orderBy('t.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function findBySearch($keyString)
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.name LIKE :ketString')
            ->setParameter('ketString', '%'. $keyString .'%')
            ->orderBy('t.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }
}
