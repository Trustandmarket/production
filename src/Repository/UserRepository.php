<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use function get_class;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    private $entityManager;
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, User::class);
        $this->entityManager = $entityManager;
    }

    public function add(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     * @param PasswordAuthenticatedUserInterface $user
     * @param string $newHashedPassword
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->add($user, true);
    }

//    /**
//     * @return User[] Returns an array of User objects
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

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * @return User[] Returns an array of User objects
     */
    public function findUsersProfessionnal(): array
    {
        return $this->createQueryBuilder('u')
            ->select("u.email_canonical as Email, u.displayName as Name, 'false' as IsExcludedFromCampaigns, 'object' as Properties")
            ->andWhere('u.roles NOT LIKE :super_admin')
            ->andWhere('u.roles NOT LIKE :role_commerce')
            ->andWhere('u.roles NOT LIKE :role_contributeur')
            ->andWhere('u.roles LIKE :role_societe OR u.roles LIKE :role_auto_entrepreneur')
            ->setParameter('super_admin', "%ROLE_SUPER_ADMIN%")
            ->setParameter('role_commerce', "%ROLE_COMMERCE%")
            ->setParameter('role_contributeur', "%ROLE_CONTRIBUTEUR%")
            ->setParameter('role_societe', "%ROLE_SOCIETE%")
            ->setParameter('role_auto_entrepreneur', "%ROLE_AUTO_ENTREPRENEUR%")
            ->getQuery()
            ->getArrayResult()
            ;
    }

    /**
     * @return User[] Returns an array of User objects
     */
    public function findUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->select("u.email_canonical as Email, u.displayName as Name, 'false' as IsExcludedFromCampaigns, 'object' as Properties")
            ->andWhere('u.roles NOT LIKE :super_admin')
            ->andWhere('u.roles NOT LIKE :role_commerce')
            ->andWhere('u.roles NOT LIKE :role_contributeur')
            ->setParameter('super_admin', "%ROLE_SUPER_ADMIN%")
            ->setParameter('role_commerce', "%ROLE_COMMERCE%")
            ->setParameter('role_contributeur', "%ROLE_CONTRIBUTEUR%")
            ->getQuery()
            ->getArrayResult()
            ;
    }

    /**
     * @return User[] Returns an array of User objects
     */
    public function findMailjetUsersForUpdate(): array
    {
        return $this->createQueryBuilder('u')
            ->from('User','u')
            ->select("u.id, u.email_canonical as Email, u.displayName as Name, 'false' as IsExcludedFromCampaigns,
             'object' as Properties, u.enabled as cpteactive, 'false' as kyc, 'false' as idmangopay,u.roles as typeuser")
            ->andWhere('u.roles NOT LIKE :super_admin')
            ->andWhere('u.roles NOT LIKE :role_commerce')
            ->andWhere('u.roles NOT LIKE :role_contributeur')
            ->setParameter('super_admin', "%ROLE_SUPER_ADMIN%")
            ->setParameter('role_commerce', "%ROLE_COMMERCE%")
            ->setParameter('role_contributeur', "%ROLE_CONTRIBUTEUR%")
            ->getQuery()
            ->getArrayResult()
            ;
    }

    public function loadUserByIdentifier(string $usernameOrEmail): ?User
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT u
                FROM App\Entity\User u
                JOIN App\Entity\Abonnement a
                WHERE u.displayName = :query
                OR u.email_canonical = :query'
        )
            ->setParameter('query', $usernameOrEmail)
            ->getOneOrNullResult();
    }

    public function loadUserByUsername(string $usernameOrEmail): ?User
    {
        return $this->loadUserByIdentifier($usernameOrEmail);
    }
}
