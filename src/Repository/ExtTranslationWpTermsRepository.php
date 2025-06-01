<?php

namespace App\Repository;

use App\Entity\ExtTranslationWpTerms;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExtTranslationWpTerms>
 *
 * @method ExtTranslationWpTerms|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExtTranslationWpTerms|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExtTranslationWpTerms[]    findAll()
 * @method ExtTranslationWpTerms[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExtTranslationWpTermsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtTranslationWpTerms::class);
    }

    public function add(ExtTranslationWpTerms $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtTranslationWpTerms $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ExtTranslationWpTerms[] Returns an array of ExtTranslationWpTerms objects
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

//    public function findOneBySomeField($value): ?ExtTranslationWpTerms
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
