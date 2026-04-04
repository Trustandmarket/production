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
        $principalActivity = $this->serviceManager->readUserMeta($userId, 'activite_principale');

        if (!$principalActivity || !$principalActivity->getMetaValue()) {
            return '';
        }

        $principalActivity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy([
            'termTaxonomyId' => $principalActivity->getMetaValue(),
        ]);

        if (!$principalActivity instanceof WpTermTaxonomy) {
            return '';
        }

        if ((string) $principalActivity->getDescription() !== '') {
            return (string) $principalActivity->getDescription();
        }

        foreach ($this->serviceManager->postCategorie1('product_activity') as $activity) {
            if ((string) ($activity->termId ?? '') === (string) $principalActivity->getTermId()) {
                return (string) ($activity->name ?? '');
            }
        }

        return '';
    }
}
