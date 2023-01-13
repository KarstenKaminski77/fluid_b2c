<?php

namespace App\Repository;

use App\Entity\DistributorClinics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DistributorClinics>
 *
 * @method DistributorClinics|null find($id, $lockMode = null, $lockVersion = null)
 * @method DistributorClinics|null findOneBy(array $criteria, array $orderBy = null)
 * @method DistributorClinics[]    findAll()
 * @method DistributorClinics[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DistributorClinicsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DistributorClinics::class);
    }

    public function add(DistributorClinics $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DistributorClinics $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function adminFindAll($distributorId): array
    {
        $queryBuilder = $this->createQueryBuilder('dc')
            ->select('dc', 'c')
            ->join('dc.clinic', 'c')
            ->andWhere('dc.distributor = :distributorId')
            ->setParameter('distributorId',$distributorId)
            ->orderBy('dc.id', 'DESC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

//    /**
//     * @return DistributorClinics[] Returns an array of DistributorClinics objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DistributorClinics
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
