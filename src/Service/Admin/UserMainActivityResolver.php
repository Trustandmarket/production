<?php

namespace App\Service\Admin;

use App\Entity\WpTermTaxonomy;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;

class UserMainActivityResolver
{
    public function __construct(
        private readonly ServiceManager $serviceManager,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function resolveLabel(int $userId): string
    {
        $activityTaxonomyId = $this->serviceManager->getUserStringDataValue($userId, 'activite_principale');

        if ($activityTaxonomyId === '') {
            return '';
        }

        $activityTaxonomy = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy([
            'termTaxonomyId' => $activityTaxonomyId,
        ]);

        if (!$activityTaxonomy instanceof WpTermTaxonomy) {
            return '';
        }

        $termId = $activityTaxonomy->getTermId();
        if ($termId === null) {
            return '';
        }

        foreach ($this->serviceManager->postCategorie1('product_activity') as $activity) {
            if ((string) ($activity->termId ?? '') === (string) $termId) {
                return (string) ($activity->name ?? '');
            }
        }

        return '';
    }
}
