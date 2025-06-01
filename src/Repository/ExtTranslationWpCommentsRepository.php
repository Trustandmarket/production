<?php

namespace App\Repository;

use App\Entity\ExtTranslationWpComments;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtTranslationWpComments>
 *
 * @method ExtTranslationWpComments|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtTranslationWpComments|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtTranslationWpComments[]    findAll()
 * @method ExtTranslationWpComments[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtTranslationWpCommentsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtTranslationWpComments::class);
    }

    public function add(ExtTranslationWpComments $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtTranslationWpComments $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ExtTranslationWpComments[] Returns an array of ExtTranslationWpComments objects
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

//    public function findOneBySomeField($value): ?ExtTranslationWpComments
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
