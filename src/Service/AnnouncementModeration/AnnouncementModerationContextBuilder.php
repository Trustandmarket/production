<?php

namespace App\Service\AnnouncementModeration;

use App\Entity\User;
use App\Entity\WpPosts;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;

class AnnouncementModerationContextBuilder
{
    private const META_KEYS = [
        '_price',
        '_product_country',
        '_product_adress',
        '_product_code_postal',
        '_product_precision',
        '_product_city',
        '_product_has_equipments_bureau',
        '_product_has_equipments_wifi',
        '_product_has_equipments_cofe',
        '_product_other_equipments',
        '_product_image_gallery',
        'images_annonces',
        '_product_video',
        '_product_devise',
    ];

    public function __construct(
        private ServiceManager $serviceManager,
        private EntityManagerInterface $em
    ) {
    }

    public function buildForAnnouncement(int $announcementId): AnnouncementModerationContext
    {
        $announcement = $this->em->getRepository(WpPosts::class)->find($announcementId);

        if (!$announcement instanceof WpPosts) {
            throw new \RuntimeException(sprintf('Annonce %d introuvable.', $announcementId));
        }

        $owner = $this->em->getRepository(User::class)->find($announcement->getPostAuthor());
        $aggregateData = $this->serviceManager->readAllAnnonceData($announcementId);

        if (!is_array($aggregateData)) {
            $aggregateData = [];
        }

        $meta = [];
        foreach (self::META_KEYS as $metaKey) {
            $meta[$metaKey] = trim((string) $this->serviceManager->getPostStringDataValue((string) $announcementId, $metaKey));
        }

        return new AnnouncementModerationContext(
            $announcement,
            $owner instanceof User ? $owner : null,
            $aggregateData,
            $meta
        );
    }
}
