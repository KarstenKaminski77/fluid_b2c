<?php

namespace App\Repository;

use App\Entity\ActiveIngredients;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActiveIngredients>
 *
 * @method ActiveIngredients|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActiveIngredients|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActiveIngredients[]    findAll()
 * @method ActiveIngredients[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActiveIngredientsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiveIngredients::class);
    }

    public function add(ActiveIngredients $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActiveIngredients $entity, bool $flush = false): void
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
     * @return ActiveIngredients[] Returns an array of ActiveIngredient objects
     */
    public function findBySearch($keyword)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :keyword')
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('a.name', 'ASC');

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

//    /**
//     * @return ActiveIngredients[] Returns an array of ActiveIngredients objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ActiveIngredients
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
