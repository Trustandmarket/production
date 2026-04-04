<?php

namespace App\Service\Admin;

use App\Service\ServiceManager;

class UserMainActivityResolver
{
    public function __construct(
        private readonly ServiceManager $serviceManager
    ) {
    }

    public function resolveLabel(int $userId): string
    {
        $activityTermId = $this->serviceManager->getUserStringDataValue($userId, 'activite_principale');

        if ($activityTermId === '') {
            return '';
        }

        foreach ($this->serviceManager->postCategorie1('product_activity') as $activity) {
            if ((string) ($activity->termId ?? '') === (string) $activityTermId) {
                return (string) ($activity->name ?? '');
            }
        }

        return '';
    }
}
