<?php

namespace App\Repository;

use App\Entity\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Products|null find($id, $lockMode = null, $lockVersion = null)
 * @method Products|null findOneBy(array $criteria, array $orderBy = null)
 * @method Products[]    findAll()
 * @method Products[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductsRepository extends EntityRepository
{
    private $em;

    /**
    * @return Products[] Returns an array of Products objects
    */
    public function findBySearch($keyword)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name LIKE :keyword')
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Products[] Returns an array of Products objects
     */
    public function findBySearchAvailable($keyword)
    {
        return $this->createQueryBuilder('p')
            ->select('p','dp','d','c','pm','pi')
            ->join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->join('p.productsSpecies', 'ps')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.productManufacturers', 'pm')
            ->leftJoin('p.productFavourites', 'pf')
            ->leftJoin('p.productImages', 'pi')
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
            ->setParameter('search_term', '*'.$keyword.'*')
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->andWhere("dp.itemId != ''")
            ->getQuery()
            ->getResult();
            ;
    }

    /**
     * @return Products[] Returns an array of Products objects
     */
    public function findBySearchAdmin($keyword)
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->andWhere('p.name LIKE :keyword')
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('p.name', 'ASC');

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findByKeyString($keywords, $countryId)
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));";
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p','dp','d','c','pm','pi')
            ->join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->join('p.productsSpecies', 'ps')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.productManufacturers', 'pm')
            ->leftJoin('p.productFavourites', 'pf')
            ->leftJoin('p.productImages', 'pi')
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
            ->setParameter('search_term', '*'.$keywords.'*')
            ->andWhere('d.addressCountry = :countryId')
            ->setParameter('countryId', $countryId)
            ->andWhere('p.isPublished = 1')
            ->andWhere('p.isActive = 1')
            ->andWhere("dp.itemId != ''");

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findByFilter($arraySearch, $level)
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p','dp','d','pm','pi')
            ->Join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->join('p.productManufacturers', 'pm')
            ->leftJoin('p.productFavourites', 'pf')
            ->leftJoin('p.productImages', 'pi')
            ->andWhere("MATCH_AGAINST(p.name,p.activeIngredient,p.description,p.slug) AGAINST(:search_term boolean) > 0")
            ->setParameter('search_term', '*'.$arraySearch[0]['categoryKeyword'].'*');

        // Categories
        if(array_key_exists('category', $arraySearch[0])) {

            if (array_key_exists('categoryId', $arraySearch[0]['category'][0])) {

                if ($level == 1) {

                    $queryBuilder
                        ->andWhere("p.category = :categoryId")
                        ->setParameter('categoryId', $arraySearch[0]['category'][0]['categoryId']);
                }

                if ($level == 2) {

                    $queryBuilder
                        ->andWhere("p.category2 = :categoryId")
                        ->setParameter('categoryId', $arraySearch[0]['category'][0]['categoryId']);
                }

                if ($level == 3) {

                    $queryBuilder
                        ->andWhere("p.category3 = :categoryId")
                        ->setParameter('categoryId', $arraySearch[0]['category'][0]['categoryId']);
                }
            }
        }

        // Manufacturers
        if(array_key_exists('selectedManufacturers', $arraySearch[0])){

            $queryBuilder
                ->andWhere('pm.manufacturers in (:manufacturerIds)')
                ->setParameter('manufacturerIds', $arraySearch[0]['selectedManufacturers']);
        }

        // Distributors
        if(array_key_exists('selectedDistributors', $arraySearch[0])){

            $queryBuilder
                ->andWhere('dp.distributor in (:distributorIds)')
                ->setParameter('distributorIds', $arraySearch[0]['selectedDistributors']);
        }

        // Favourites
        if($arraySearch[0]['favourite'] == 'true'){

            $queryBuilder
                ->andWhere('pf.clinic = :clinic')
                ->setParameter('clinic', $arraySearch[0]['clinicId']);
        }

        // In Stock
        if($arraySearch[0]['inStock'] == 'true'){

            $queryBuilder
                ->andWhere('dp.stockCount > 0');
        }

        $queryBuilder
            ->andWhere('p.isPublished = 1');

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findByListId($product_ids)
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p','dp','d','c','pm','pi')
            ->Join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->join('p.category', 'c')
            ->join('p.productManufacturers', 'pm')
            ->leftJoin('p.productFavourites', 'pf')
            ->leftJoin('p.productImages', 'pi')
            ->andWhere("dp.product IN (:product_ids)")
            ->setParameter('product_ids', $product_ids)
            ->andWhere('p.isPublished = 1');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function adminFindAll()
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p')
            ->andWhere('p.isActive = 1')
            ->orderBy('p.name', 'ASC');
        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }

    public function findByRand()
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p','dp','d','pi', 'RAND() as HIDDEN rand')
            ->join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->leftJoin('p.productImages', 'pi')
            ->andWhere('pi.isDefault = 1')
            ->andWhere('p.isActive = 1')
            ->andWhere('p.isPublished = 1')
            //->groupBy('dp.id')
            ->orderBy('rand')
            ->setMaxResults(10);

        return $queryBuilder->getQuery()->getResult();

        $conn = $this->em->getConnection();
        $res = $conn->prepare($sql)->executeQuery()->fetchAll();

        return $res;
    }

    public function findByManufacturer($distributorId, $manufacturerId, $speciesId):array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p','dp','d','pm')
            ->join('p.distributorProducts', 'dp')
            ->join('dp.distributor', 'd')
            ->leftJoin('p.productsSpecies', 'ps')
            ->leftJoin('p.productManufacturers', 'pm')
            ->andWhere('p.isActive = 1')
            ->andWhere('p.isPublished = 1')
            ->andWhere('dp.distributor = :distributorId')
            ->setParameter('distributorId', $distributorId);

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

        $queryBuilder
            ->orderBy('p.name', 'DESC')
        ;

        return [$queryBuilder->getQuery(), $queryBuilder->getQuery()->getResult()];
    }
}
