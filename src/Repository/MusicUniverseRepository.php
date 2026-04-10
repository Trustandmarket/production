<?php

namespace App\Repository;

use App\Entity\MusicUniverse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicUniverse>
 *
 * @method MusicUniverse|null find($id, $lockMode = null, $lockVersion = null)
 * @method MusicUniverse|null findOneBy(array $criteria, array $orderBy = null)
 * @method MusicUniverse[]    findAll()
 * @method MusicUniverse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MusicUniverseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicUniverse::class);
    }
}

