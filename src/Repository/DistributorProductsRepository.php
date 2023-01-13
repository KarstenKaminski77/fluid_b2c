<?php

namespace App\Repository;

use App\Entity\DistributorProducts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DistributorProducts|null find($id, $lockMode = null, $lockVersion = null)
 * @method DistributorProducts|null findOneBy(array $criteria, array $orderBy = null)
 * @method DistributorProducts[]    findAll()
 * @method DistributorProducts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DistributorProductsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DistributorProducts::class);
    }

    public function getProductStockCount($product_id): array
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->select('sum(d.stockCount)')
            ->andWhere('d.product = :product_id')
            ->setParameter('product_id', $product_id)
            ->groupBy('d.product')
            ->setMaxResults(1);
        return $queryBuilder->getQuery()->getResult();
    }

    public function findByDistributorProductStockCount($product_id, $distributor_id): array
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->select('sum(d.stockCount) as stock_count')
            ->andWhere('d.product = :product_id')
            ->setParameter('product_id', $product_id)
            ->andWhere('d.distributor = :distributor_id')
            ->setParameter('distributor_id', $distributor_id)
            ->groupBy('d.product')
            ->setMaxResults(1);
        return $queryBuilder->getQuery()->getResult();
    }

    public function getLowestPrice($product_id): array
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->select('d.unitPrice')
            ->andWhere('d.product = :product_id')
            ->setParameter('product_id', $product_id)
            ->orderBy('d.unitPrice', 'ASC')
            ->setMaxResults(1);
        return $queryBuilder->getQuery()->getResult();
    }

    public function findWithItemId($product_id): array
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->select('d')
            ->andWhere('d.product = :product_id')
            ->setParameter('product_id', $product_id)
            ->andWhere("d.itemId != ''");

        return $queryBuilder->getQuery()->getResult();
    }

    public function getDistributorProducts($product_id, $distributor_ids): array
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->select('d')
            ->andWhere('d.product = :product_id')
            ->setParameter('product_id', $product_id)
            ->andWhere('d.distributor IN (:distributors)')
            ->setParameter('distributors', $distributor_ids);
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return DistributorProducts[] Returns an array of DistributorUsers objects
     */
    public function findDistributorProducts($keywords)
    {
        return $this->createQueryBuilder('d')
            ->select('d','p')
            ->join('d.product', 'p')
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:searchTerm boolean) > 0")
            ->setParameter('searchTerm', '*'.$keywords.'*')
            ->orderBy('p.name', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByProductDistributorId($productId, $distributorId): array
    {
        return $queryBuilder = $this->createQueryBuilder('d')
            ->select('d','p')
            ->join('d.product', 'p')
            ->andWhere('d.product = :productId')
            ->setParameter('productId', $productId)
            ->andWhere('d.distributor = :distributorId')
            ->setParameter('distributorId', $distributorId)
            ->andWhere("p.isActive = 1")
            ->andWhere("p.isPublished = 1")
            ->getQuery()->getResult();
    }
}
