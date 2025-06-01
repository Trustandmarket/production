<?php

namespace App\Repository;

use App\Entity\Abonnement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Abonnement>
 *
 * @method Abonnement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Abonnement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Abonnement[]    findAll()
 * @method Abonnement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbonnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abonnement::class);
    }

    /**
     * @param $value
     * @return Abonnement[] Returns an array of Abonnement objects
     */
    public function findAbonnementByUser($value): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.offre', 'offre')
            ->andWhere('a.user = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'desc')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param $value
     * @return Abonnement[] Returns an array of Abonnement objects
     */
    public function findAllActiveAbonnementWithUser($value): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.offre', 'offre')
            ->leftJoin('a.user', 'user')
            ->andWhere('a.abonnementActif = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'desc')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @param $value
     * @return Abonnement[] Returns an array of Abonnement objects
     */
    public function findAllActiveAboEndedWithUser($value): array
    {
        // Define today's date
        $dateAujourdhui = new \DateTime();

        return $this->createQueryBuilder('a')
            ->leftJoin('a.offre', 'offre')
            ->leftJoin('a.user', 'user')
            ->where('a.abonnementActif = :val')
            ->setParameter('val', $value)
            ->andWhere('offre.titre != :val2')
            ->setParameter('val2', 'GRATUIT')
            // Add condition where (date_fin + 1 day) - dateAujourdhui <= 0
            ->andWhere('DATE_DIFF(DATE_ADD(a.dateFin, 1, \'DAY\'), :dateAujourdhui) <= 0')
            ->setParameter('dateAujourdhui', $dateAujourdhui)
            //Tag != DOWNGRADE
            ->andWhere('a.tag != :val3')
            ->setParameter('val3', 'DOWNGRADE')
            ->andWhere('a.tag != :val4')
            ->setParameter('val4', 'PARTNER')
            ->orderBy('a.id', 'desc')
            ->getQuery()
            ->getResult();
//Ajuster une condition et dont le TAG est different de Downgrade
    }

    /**
     * @param $value
     * @return QueryBuilder Returns an array of Abonnement objects
     */
    public function findAbonnementByUserPaginate($value): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.offre', 'offre')
            ->andWhere('a.user = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'desc');
    }

    /*
     *
     */
    public function disabledOldAbonnements(User $user): int
    {
        $qb = $this->createQueryBuilder('a');

        return $qb->update( Abonnement::class,'a') // Remove the extra comma here
            ->set('a.abonnementActif', ':state')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->setParameter('state', false)
            ->getQuery()
            ->execute();
    }

//    public function findOneBySomeField($value): ?Abonnement
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
