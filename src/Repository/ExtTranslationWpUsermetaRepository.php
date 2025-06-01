<?php

namespace App\Repository;

use App\Entity\ExtTranslationWpUsermeta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtTranslationWpUsermeta>
 *
 * @method ExtTranslationWpUsermeta|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtTranslationWpUsermeta|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtTranslationWpUsermeta[]    findAll()
 * @method ExtTranslationWpUsermeta[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtTranslationWpUsermetaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtTranslationWpUsermeta::class);
    }

    public function add(ExtTranslationWpUsermeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtTranslationWpUsermeta $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ExtTranslationWpUsermeta[] Returns an array of ExtTranslationWpUsermeta objects
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

//    public function findOneBySomeField($value): ?ExtTranslationWpUsermeta
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
