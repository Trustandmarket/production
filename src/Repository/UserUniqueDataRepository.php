<?php

namespace App\Repository;

use App\Entity\UserUniqueData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserUniqueData>
 *
 * @method UserUniqueData|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserUniqueData|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserUniqueData[]    findAll()
 * @method UserUniqueData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserUniqueDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserUniqueData::class);
    }

//    /**
//     * @return UserUniqueData[] Returns an array of UserUniqueData objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UserUniqueData
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
