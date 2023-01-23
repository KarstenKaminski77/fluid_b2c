<?php

namespace App\Repository;

use App\Entity\Articles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Articles>
 *
 * @method Articles|null find($id, $lockMode = null, $lockVersion = null)
 * @method Articles|null findOneBy(array $criteria, array $orderBy = null)
 * @method Articles[]    findAll()
 * @method Articles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticlesRepository extends EntityRepository
{
    public function add(Articles $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Articles $entity, bool $flush = false): void
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

    public function findByPageId($pageId)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('a', 'ad')
            ->join('a.articleDetails', 'ad')
            ->andWhere('a.page = :pageId')
            ->setParameter('pageId', $pageId)
            ->orderBy('a.name', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

    public function findByArticleId($articleId)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('a', 'ad', 'u')
            ->join('a.articleDetails', 'ad')
            ->join('ad.user', 'u')
            ->andWhere('ad.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('ad.name', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }

//    /**
//     * @return Articles[] Returns an array of Articles objects
//     */
//    public function findUsersByld($articleId): array
//    {
//        return $this->createQueryBuilder('a')
//            ->select('a', 'ad')
//            ->join('a.articleDetails', 'ad')
//            ->andWhere('a.id = :articleId')
//            ->setParameter('articleId', $articleId)
//            ->orderBy('a.id', 'ASC')
//            ->addGroupBy('ad.user')
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Articles
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
