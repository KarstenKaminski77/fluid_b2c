<?php

namespace App\Repository;

use App\Entity\RestrictedDomains;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RestrictedDomains>
 *
 * @method RestrictedDomains|null find($id, $lockMode = null, $lockVersion = null)
 * @method RestrictedDomains|null findOneBy(array $criteria, array $orderBy = null)
 * @method RestrictedDomains[]    findAll()
 * @method RestrictedDomains[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RestrictedDomainsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestrictedDomains::class);
    }

    public function add(RestrictedDomains $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RestrictedDomains $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->select('r')
            ->orderBy('r.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @return RestrictedDomains[] Returns an array of ActiveIngredient objects
     */
    public function findBySearch($keyword)
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->andWhere('r.name LIKE :keyword')
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('r.name', 'ASC');

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @return RestrictedDomains[] Returns an array of RestrictedDomains objects
     */
    public function arrayFindAll(): array
    {
        return $this->createQueryBuilder('r')
            ->getQuery()
            ->getResult()
        ;
    }

//    public function findOneBySomeField($value): ?RestrictedDomains
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
