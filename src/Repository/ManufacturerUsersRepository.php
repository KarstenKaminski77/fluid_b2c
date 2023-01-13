<?php

namespace App\Repository;

use App\Entity\ManufacturerUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ManufacturerUsers>
 *
 * @method ManufacturerUsers|null find($id, $lockMode = null, $lockVersion = null)
 * @method ManufacturerUsers|null findOneBy(array $criteria, array $orderBy = null)
 * @method ManufacturerUsers[]    findAll()
 * @method ManufacturerUsers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ManufacturerUsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ManufacturerUsers::class);
    }

    public function add(ManufacturerUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ManufacturerUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ManufacturerUsers[] Returns an array of ManufacturerUsers objects
     */
    public function findManufacturerUsers($manufacturerId)
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.manufacturer = :manufacturerId')
            ->setParameter('manufacturerId', $manufacturerId)
            ->orderBy('m.id', 'DESC')
        ;

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }
}
