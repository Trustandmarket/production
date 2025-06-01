<?php

namespace App\Repository;

use App\Entity\ExtTranslationWpPostmeta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtTranslationWpPostmeta>
 *
 * @method ExtTranslationWpPostmeta|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtTranslationWpPostmeta|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtTranslationWpPostmeta[]    findAll()
 * @method ExtTranslationWpPostmeta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtTranslationWpPostmetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtTranslationWpPostmeta::class);
    }

    public function add(ExtTranslationWpPostmeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtTranslationWpPostmeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ExtTranslationWpPostmeta[] Returns an array of ExtTranslationWpPostmeta objects
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

//    public function findOneBySomeField($value): ?ExtTranslationWpPostmeta
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
