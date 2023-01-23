<?php

namespace App\Repository;

use App\Entity\Banners;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Banners>
 *
 * @method Banners|null find($id, $lockMode = null, $lockVersion = null)
 * @method Banners|null findOneBy(array $criteria, array $orderBy = null)
 * @method Banners[]    findAll()
 * @method Banners[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BannersRepository extends EntityRepository
{
    public function add(Banners $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Banners $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->select('b')
            ->orderBy('b.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findBySearch($keyString)
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->select('b')
            ->andWhere('b.name LIKE :ketString')
            ->setParameter('ketString', '%'. $keyString .'%')
            ->orderBy('b.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findHomePage()
    {
        $queryBuilder = $this->createQueryBuilder('b')
            ->select('b')
            ->andWhere('b.isPublished = 1')
            ->andWhere('b.page = 1')
            ->orderBy('b.isDefault', 'DESC')
            ->addOrderBy('b.orderBy', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }

//    /**
//     * @return Banners[] Returns an array of Banners objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Banners
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
