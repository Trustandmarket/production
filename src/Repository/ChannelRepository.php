<?php

namespace App\Repository;

use App\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 *
 * @method Channel|null find($id, $lockMode = null, $lockVersion = null)
 * @method Channel|null findOneBy(array $criteria, array $orderBy = null)
 * @method Channel[]    findAll()
 * @method Channel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
        $this->em = $this->getEntityManager()->getConnection();
    }

    /**
     * @return Channel[] Returns an array of Channel objects of a user
     */
    public function findAllByUserId($user_id)
    {
        $sql = "SELECT DISTINCT wp_channels.id, (SELECT wp_messages.sent_date FROM wp_messages WHERE wp_messages.channel_id = wp_channels.id ORDER BY wp_messages.sent_date DESC LIMIT 1) as sent_date FROM wp_channels WHERE wp_channels.first_user_id = :user_id OR wp_channels.second_user_id = :user_id ORDER BY sent_date DESC";
        $channels = $this->em->prepare($sql);
        $channels = $channels->execute(['user_id'=>$user_id]);
        $channels = $channels->fetchAllAssociative();

        $channelsArray = [];
        foreach ($channels as $value) {
            $qb = $this->createQueryBuilder('c')
                ->Where('c.id = :id')
                ->setParameter('id', $value['id'])
                ->getQuery()
                ->getOneOrNullResult();
            $channelsArray[] = $qb;
        }

        return $channelsArray;
    }

    /**
     * @return Channel[] Returns an array of Channel objects of a user
     */
    public function findOneByUsers($user_id, $user2_id)
    {
        $sql = "SELECT wp_channels.id FROM wp_channels WHERE (wp_channels.first_user_id = :user_id AND wp_channels.second_user_id = :user2_id) OR (wp_channels.first_user_id = :user2_id AND wp_channels.second_user_id = :user_id)";
        $channel = $this->em->prepare($sql);
        $channel = $channel->execute(['user_id'=>$user_id,'user2_id'=>$user2_id]);
        $channel = $channel->fetch();

        if ($channel) {
            return $this->createQueryBuilder('c')
                ->Where('c.id = :id')
                ->setParameter('id', $channel['id'])
                ->getQuery()
                ->getOneOrNullResult();
        } else {
            return null;
        }
    }

    public function add(Channel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Channel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Channel[] Returns an array of Channel objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Channel
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
