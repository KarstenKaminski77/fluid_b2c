<?php

namespace App\Repository;

use App\Entity\Lists;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Lists|null find($id, $lockMode = null, $lockVersion = null)
 * @method Lists|null findOneBy(array $criteria, array $orderBy = null)
 * @method Lists[]    findAll()
 * @method Lists[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ListsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lists::class);
    }

    public function getClinicLists($clinic_id): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l,li')
            ->leftJoin('l.listItems', 'li')
            ->andWhere('l.clinic = :clinic_id')
            ->setParameter('clinic_id', $clinic_id)
            ->andWhere('l.listType != :list_type')
            ->setParameter('list_type', 'favourite');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getIndividualList($list_id): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l','li','d','dp','p')
            ->leftJoin('l.listItems', 'li')
            ->leftJoin('li.distributorProduct', 'dp')
            ->leftJoin('dp.product', 'p')
            ->leftJoin('dp.distributor', 'd')
            ->andWhere('l.id = :list_id')
            ->setParameter('list_id', $list_id)
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->andWhere("li.itemId != ''")
            ->orderBy('p.name', 'ASC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function findWithItemId($clinicId): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l','li','p')
            ->leftJoin('l.listItems', 'li')
            ->leftJoin('li.product', 'p')
            ->andWhere('l.clinic = :clinicId')
            ->setParameter('clinicId', $clinicId)
            ->andWhere('l.listType != :retailList')
            ->setParameter('retailList', 'retail')
            ->orderBy('l.isProtected', 'DESC')
            ->addOrderBy('l.name', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

    public function findClinicProducts($clinicId, $listId)
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l','li', 'p')
            ->join('l.listItems', 'li')
            ->join('li.product', 'p')
            ->andWhere('l.clinic = :clinicId')
            ->setParameter('clinicId', $clinicId)
            ->andWhere('l.id = :listId')
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->setParameter('listId', $listId)
            ->orderBy('l.id', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }
}
