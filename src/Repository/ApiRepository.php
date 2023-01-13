<?php

namespace App\Repository;

use App\Entity\Api;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Api>
 *
 * @method Api|null find($id, $lockMode = null, $lockVersion = null)
 * @method Api|null findOneBy(array $criteria, array $orderBy = null)
 * @method Api[]    findAll()
 * @method Api[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Api::class);
    }

    public function add(Api $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Api $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('a')
            ->orderBy('a.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @return Api[] Returns an array of ActiveIngredient objects
     */
    public function findBySearch($keyword)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :keyword')
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('a.name', 'ASC');

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

//    public function findOneBySomeField($value): ?Api
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
