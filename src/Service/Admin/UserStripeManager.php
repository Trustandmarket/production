<?php

namespace App\Service\Admin;

use App\Entity\WpUsermeta;
use App\Service\Payment;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;

class UserStripeManager
{
    private Payment $payment;
    private ServiceManager $serviceManager;
    private EntityManagerInterface $em;

    public function __construct(Payment $payment, ServiceManager $serviceManager, EntityManagerInterface $em)
    {
        $this->payment = $payment;
        $this->serviceManager = $serviceManager;
        $this->em = $em;
    }

    public function deleteStripeAccountById(string $stripeId): array
    {
        $deletedItem = $this->payment->deleteStripeUser($stripeId);

        if (($deletedItem['deleted'] ?? false) !== true) {
            return $deletedItem;
        }

        $userMetaStripe = $this->em->getRepository(WpUsermeta::class)->findOneByMetaValue($stripeId);

        if ($userMetaStripe) {
            $this->em->remove($userMetaStripe);
            $this->em->flush();
        }

        return $deletedItem;
    }

    public function deleteStripeDataForUser(int $userId): void
    {
        $stripeAccountId = $this->serviceManager->getUserStringDataValue($userId, 'mp_user_id_sandbox');
        $stripePersonId = $this->serviceManager->getUserStringDataValue($userId, 'stripe_person_user');

        if (!empty($stripePersonId) && !empty($stripeAccountId)) {
            $this->payment->deleteStripePerson($stripeAccountId, $stripePersonId);
        }

        if (!empty($stripePersonId)) {
            $this->serviceManager->deleteAllUsermetaByUserIdKey($userId, 'stripe_person_user');
        }

        if (!empty($stripeAccountId)) {
            $this->payment->deleteStripeUser($stripeAccountId);
            $this->serviceManager->deleteAllUsermetaByUserIdKey($userId, 'mp_user_id_sandbox');
        }
    }
}
