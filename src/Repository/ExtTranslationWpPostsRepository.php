<?php

namespace App\Repository;

use App\Entity\ExtTranslationWpPosts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtTranslationWpPosts>
 *
 * @method ExtTranslationWpPosts|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtTranslationWpPosts|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtTranslationWpPosts[]    findAll()
 * @method ExtTranslationWpPosts[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtTranslationWpPostsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtTranslationWpPosts::class);
    }

    public function add(ExtTranslationWpPosts $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtTranslationWpPosts $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ExtTranslationWpPosts[] Returns an array of ExtTranslationWpPosts objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ExtTranslationWpPosts
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
