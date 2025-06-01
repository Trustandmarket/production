<?php
// src/EventListener/UserChangedNotifier.php
namespace App\EventListener;

use App\Entity\User;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UserPreUpdate
{
// the entity listener methods receive two arguments:
// the entity instance and the lifecycle event
    public function preUpdate(User $user, LifecycleEventArgs $event): void
    {
        $user->setUpdatedAt(new \DateTime());
    }
}