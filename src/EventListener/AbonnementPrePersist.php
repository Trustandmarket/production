<?php
// src/EventListener/UserChangedNotifier.php
namespace App\EventListener;

use App\Entity\Abonnement;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class AbonnementPrePersist
{
    private $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
// the entity listener methods receive two arguments:
// the entity instance and the lifecycle event
    public function prePersist(Abonnement $abonnement, LifecycleEventArgs $event): void
    {
        if($abonnement->isAbonnementActif()){
            //Mise a jour des anciens abonnements
            $this->entityManager->getRepository(Abonnement::class)->disabledOldAbonnements($abonnement->getUser());
        }
    }
}