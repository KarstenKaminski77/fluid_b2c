<?php

namespace App\Repository;

use App\Entity\Categories;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Categories|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categories|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categories[]    findAll()
 * @method Categories[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategoriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categories::class);
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->orderBy('c.category', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findNoParent($categoryId)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.parent is null')
            ->andWhere('c.isRoot = 0')
            ->andWhere('c.id != :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('c.category', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function findParent($categoryId)
    {
        $sql = "
            SELECT
                category
            FROM
                categories c
            WHERE
                c.id = $categoryId
        ";

        $conn = $this->_em->getConnection();
        $res = $conn->prepare($sql)->executeQuery()->fetchAssociative();

        return $res;
    }

    public function findChildByParentId($rootId, $categoryId)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.parent is null')
            ->orWhere("c.id != $categoryId AND c.rootId = $rootId");

        return $queryBuilder->getQuery()->getResult();
    }

    public function findSelected($categories)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.id in (:categories)')
            ->setParameter('categories', $categories);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findParentList($categories, $categoryId)
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.id not in (:categories)')
            ->setParameter('categories', $categories)
            ->andWhere('c.id != :categoryId')
            ->setParameter('categoryId', (int) $categoryId);

        return $queryBuilder->getQuery()->getResult();
    }
}
