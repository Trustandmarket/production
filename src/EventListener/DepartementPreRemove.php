<?php
// src/EventListener/UserChangedNotifier.php
namespace App\EventListener;

use App\Entity\Departement;
use DateTime;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DepartementPreRemove
{
// the entity listener methods receive two arguments:
// the entity instance and the lifecycle event
    public function preRemove(Departement $departement, LifecycleEventArgs $event): void
    {
        $datas = $departement->getUserUniqueData();
        foreach ($datas as $d) {
            $d->setDepartement(null);
        }
    }
}