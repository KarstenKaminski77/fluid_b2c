<?php

namespace App\Repository;

use App\Entity\Categories3;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categories3>
 *
 * @method Categories3|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categories3|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categories3[]    findAll()
 * @method Categories3[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class Categories3Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categories3::class);
    }

    public function add(Categories3 $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Categories3 $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParent($parentId, $keyword)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c','p')
            ->join('c.products', 'p')
            ->andWhere('c.category2 = :parentId')
            ->setParameter('parentId', $parentId)
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
            ->setParameter('search_term', '*'.$keyword.'*')
            ->andWhere('c.productCount > 0')
            ->orderBy('c.name', 'ASC');
        return ['',$queryBuilder->getQuery()->getResult()];
    }

//    /**
//     * @return Categories2[] Returns an array of Categories2 objects
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

//    public function findOneBySomeField($value): ?Categories2
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
