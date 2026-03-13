<?php

namespace App\Repository;

use App\Entity\ReminderLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReminderLog>
 */
class ReminderLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReminderLog::class);
    }

    public function findLatestSentForUser(User $user): ?ReminderLog
    {
        return $this->createQueryBuilder('rl')
            ->andWhere('rl.user = :user')
            ->andWhere('rl.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'sent')
            ->orderBy('rl.sentAt', 'DESC')
            ->addOrderBy('rl.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
