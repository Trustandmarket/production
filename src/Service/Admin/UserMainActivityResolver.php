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
        $storedValue = $this->serviceManager->getUserStringDataValue($userId, 'activite_principale');

        if ($storedValue === '') {
            return '';
        }

        $possibleTermIds = [(string) $storedValue];

        $taxonomy = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy([
            'termTaxonomyId' => $storedValue,
        ]);

        if ($taxonomy instanceof WpTermTaxonomy && $taxonomy->getTermId() !== null) {
            $possibleTermIds[] = (string) $taxonomy->getTermId();
        }

        foreach ($this->serviceManager->postCategorie1('product_activity') as $activity) {
            $activityTermId = (string) ($activity->termId ?? '');
            $activityTaxonomyId = (string) ($activity->termTaxonomyId ?? '');

            if (in_array($activityTermId, $possibleTermIds, true) || in_array($activityTaxonomyId, $possibleTermIds, true)) {
                return (string) ($activity->name ?? '');
            }
        }

        return '';
    }
}
