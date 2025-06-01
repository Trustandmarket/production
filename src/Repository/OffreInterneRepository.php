<?php

namespace App\Repository;

use App\Entity\OffreInterne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OffreInterne>
 *
 * @method OffreInterne|null find($id, $lockMode = null, $lockVersion = null)
 * @method OffreInterne|null findOneBy(array $criteria, array $orderBy = null)
 * @method OffreInterne[]    findAll()
 * @method OffreInterne[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OffreInterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OffreInterne::class);
    }

//    /**
//     * @return OffreInterne[] Returns an array of OffreInterne objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?OffreInterne
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
