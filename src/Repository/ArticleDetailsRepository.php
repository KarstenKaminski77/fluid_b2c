<?php

namespace App\Repository;

use App\Entity\ArticleDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleDetails>
 *
 * @method ArticleDetails|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArticleDetails|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArticleDetails[]    findAll()
 * @method ArticleDetails[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleDetailsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleDetails::class);
    }

    public function add(ArticleDetails $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ArticleDetails $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ArticleDetails[] Returns an array of ArticleDetails objects
     */
    public function findUsersByld($articleId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SET GLOBAL sql_mode='';";
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        return $this->createQueryBuilder('a')
            ->andWhere('a.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('a.id', 'ASC')
            ->groupBy('a.user')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByLastUpdated($articleId)
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->select('a')
            ->andWhere('a.id = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('a.modified', 'DESC')
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->getResult();
    }

//    public function findOneBySomeField($value): ?ArticleDetails
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
