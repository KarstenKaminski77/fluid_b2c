<?php

namespace App\Repository;

use App\Entity\ClinicRetailUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClinicRetailUsers>
 *
 * @method ClinicRetailUsers|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClinicRetailUsers|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClinicRetailUsers[]    findAll()
 * @method ClinicRetailUsers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClinicRetailUsersRepository extends EntityRepository
{
    public function add(ClinicRetailUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClinicRetailUsers $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ClinicRetailUsers[] Returns an array of ClinicRetailUsers objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ClinicRetailUsers
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
