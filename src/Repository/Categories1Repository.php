<?php

namespace App\Repository;

use App\Entity\Categories1;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categories1>
 *
 * @method Categories1|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categories1|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categories1[]    findAll()
 * @method Categories1[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class Categories1Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categories1::class);
    }

    public function add(Categories1 $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Categories1 $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findList()
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->orderBy('c.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findFirstLevel($categories)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.productCount > 0')
            ->andWhere('c.id in (:categoryIds)')
            ->setParameter('categoryIds', $categories)
            ->orderBy('c.productCount', 'DESC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }
}
