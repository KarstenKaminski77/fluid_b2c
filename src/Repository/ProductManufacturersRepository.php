<?php

namespace App\Repository;

use App\Entity\ProductManufacturers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProductManufacturers|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductManufacturers|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductManufacturers[]    findAll()
 * @method ProductManufacturers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductManufacturersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductManufacturers::class);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(ProductManufacturers $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(ProductManufacturers $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
    * @return ProductManufacturers[] Returns an array of ProductManufacturers objects
    */
    public function findByDistributorManufacturer($distributorId)
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));";
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        return $this->createQueryBuilder('pm')
            ->select('pm','p','dp','m')
            ->join('pm.manufacturers', 'm')
            ->join('pm.products', 'p')
            ->join('p.distributorProducts', 'dp')
            ->andWhere('dp.distributor = :distributorId')
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->setParameter('distributorId', $distributorId)
            ->groupBy('m.id')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return ProductManufacturers[] Returns an array of ProductManufacturers objects
     */
    public function findByClinicList($clinicId)
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));";
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        return $this->createQueryBuilder('pm')
            ->select('pm','p','cp','m')
            ->join('pm.manufacturers', 'm')
            ->join('pm.products', 'p')
            ->join('p.clinicProducts', 'cp')
            ->andWhere('cp.clinic = :clinicId')
            ->setParameter('clinicId', $clinicId)
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->groupBy('m.id')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    /*
    public function findOneBySomeField($value): ?ProductManufacturers
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
