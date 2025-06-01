<?php

namespace App\Repository;

use App\Entity\WpCommentmeta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WpCommentmeta>
 *
 * @method WpCommentmeta|null find($id, $lockMode = null, $lockVersion = null)
 * @method WpCommentmeta|null findOneBy(array $criteria, array $orderBy = null)
 * @method WpCommentmeta[]    findAll()
 * @method WpCommentmeta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WpCommentmetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WpCommentmeta::class);
    }

    public function add(WpCommentmeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WpCommentmeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return WpCommentmeta[] Returns an array of WpCommentmeta objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('w.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?WpCommentmeta
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
