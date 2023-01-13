<?php

namespace App\Repository;

use App\Entity\Addresses;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Addresses|null find($id, $lockMode = null, $lockVersion = null)
 * @method Addresses|null findOneBy(array $criteria, array $orderBy = null)
 * @method Addresses[]    findAll()
 * @method Addresses[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressesRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Addresses::class);
        $this->conn = $this->_em->getConnection();
    }

    /**
    * @return Addresses[] Returns an array of Addresses objects
    */
    public function getAddresses($clinic_id)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->andWhere('a.clinic = :clinic_id')
            ->setParameter('clinic_id', $clinic_id)
            ->andWhere('a.isActive = 1')
            ->orderBy('a.id', 'ASC')
        ;

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @return Addresses[] Returns an array of Addresses objects
     */
    public function getRetailAddresses($retailUserId)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->andWhere('a.retail = :retailUserId')
            ->setParameter('retailUserId', $retailUserId)
            ->andWhere('a.isActive = 1')
            ->orderBy('a.id', 'ASC')
        ;

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function getRetailDefaultAddresses($retailId, $addressId)
    {
        $sql = "
            UPDATE 
                 addresses
            SET
                is_default = 0
            WHERE 
                  retail_id = $retailId
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->executeQuery();

        $sql = "
            UPDATE 
                 addresses
            SET
                is_default = 1
            WHERE 
                  id = $addressId
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->executeQuery();
    }
}
