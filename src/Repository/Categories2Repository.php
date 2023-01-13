<?php

namespace App\Repository;

use App\Entity\Categories2;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categories2>
 *
 * @method Categories2|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categories2|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categories2[]    findAll()
 * @method Categories2[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class Categories2Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categories2::class);
    }

    public function add(Categories2 $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Categories2 $entity, bool $flush = false): void
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
            ->andWhere('c.category1 = :parentId')
            ->setParameter('parentId', $parentId)
            ->andWhere('c.productCount > 0')
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
            ->setParameter('search_term', '*'.$keyword.'*')
            ->orderBy('c.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }
}
