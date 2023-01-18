<?php

namespace App\Repository;

use App\Entity\ListItems;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ListItems|null find($id, $lockMode = null, $lockVersion = null)
 * @method ListItems|null findOneBy(array $criteria, array $orderBy = null)
 * @method ListItems[]    findAll()
 * @method ListItems[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ListItemsRepository extends EntityRepository
{
    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(ListItems $entity, bool $flush = true): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(ListItems $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
    * @return ListItems[] Returns an array of ListItems objects
    */
    public function findByListId($listId)
    {
        $queryBuilder = $this->createQueryBuilder('li')
            ->select('li', 'l', 'p', 'd', 'dp', 'a')
            ->join('li.list', 'l')
            ->join('li.product', 'p')
            ->join('li.distributor', 'd')
            ->join('li.distributorProduct', 'dp')
            ->join('d.api', 'a')
            ->andWhere('li.list = :listId')
            ->setParameter('listId', $listId)
            ->andWhere("li.itemId != ''");

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @return ListItems[] Returns an array of ListItems objects
     */
    public function findByFilter($listId, $manufacturerId, $speciesId)
    {
        $queryBuilder = $this->createQueryBuilder('li')
            ->select('li', 'l', 'p','pm','ps','d', 'dp', 'a')
            ->join('li.list', 'l')
            ->join('li.product', 'p')
            ->leftJoin('p.productsSpecies', 'ps')
            ->leftJoin('p.productManufacturers', 'pm')
            ->join('li.distributor', 'd')
            ->join('li.distributorProduct', 'dp')
            ->join('d.api', 'a')
            ->andWhere('li.list = :listId')
            ->setParameter('listId', $listId)
            ->andWhere("li.itemId != ''");

        if($manufacturerId > 0)
        {
            $ids = [$manufacturerId];
            $queryBuilder
                ->andWhere('pm.manufacturers in (:manufacturerIds)')
                ->setParameter('manufacturerIds', $ids);
        }

        if($speciesId > 0)
        {
            $ids = [$speciesId];
            $queryBuilder
                ->andWhere('ps.species in (:speciesIds)')
                ->setParameter('speciesIds', $ids);
        }

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findListItem($clinicId, $listId, $productId)
    {
        return $this->createQueryBuilder('li')
            ->select('li', 'l', 'p')
            ->join('li.list', 'l')
            ->join('li.product', 'p')
            ->andWhere('li.list = :listId')
            ->setParameter('listId', $listId)
            ->andWhere('li.product = :productId')
            ->setParameter('productId', $productId)
            ->andWhere('l.clinic = :clinicId')
            ->setParameter('clinicId', $clinicId)
            ->getQuery()->getResult();
    }

    /**
     * @return ListItems[] Returns an array of ListItems objects
     */
    public function findByKeyword($listId, $keywords)
    {
        $queryBuilder = $this->createQueryBuilder('li')
            ->select('li', 'l', 'p', 'd', 'dp', 'a')
            ->join('li.list', 'l')
            ->join('li.product', 'p')
            ->join('li.distributor', 'd')
            ->join('li.distributorProduct', 'dp')
            ->join('d.api', 'a')
            ->andWhere('li.list = :listId')
            ->setParameter('listId', $listId);

        if($keywords != null)
        {
            $queryBuilder
                ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
                ->setParameter('search_term', '*'.$keywords.'*');
        }

        $queryBuilder
            ->andWhere("li.itemId != ''");

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /*
    public function findOneBySomeField($value): ?ListItems
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
