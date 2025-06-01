<?php

namespace App\Repository;

use App\Entity\WpTermRelationships;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WpTermRelationships>
 *
 * @method WpTermRelationships|null find($id, $lockMode = null, $lockVersion = null)
 * @method WpTermRelationships|null findOneBy(array $criteria, array $orderBy = null)
 * @method WpTermRelationships[]    findAll()
 * @method WpTermRelationships[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WpTermRelationshipsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WpTermRelationships::class);
    }

    public function add(WpTermRelationships $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WpTermRelationships $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return WpTermRelationships[] Returns an array of WpTermRelationships objects
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

//    public function findOneBySomeField($value): ?WpTermRelationships
//    {
//        return $this->createQueryBuilder('w')
//            ->andWhere('w.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
