<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationDecision;
use Doctrine\ORM\EntityManagerInterface;

class AnnouncementModerationStatusApplier
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array{target_status: string, changed: bool}
     */
    public function apply(AnnouncementModerationContext $context, AnnouncementModerationDecision $decision): array
    {
        $announcement = $context->getAnnouncement();
        $targetStatus = $this->resolveTargetStatus($decision);
        $currentStatus = (string) $announcement->getPostStatus();

        if ($currentStatus === $targetStatus) {
            return [
                'target_status' => $targetStatus,
                'changed' => false,
            ];
        }

        $now = new \DateTimeImmutable();

        $announcement->setPostStatus($targetStatus);
        $announcement->setPostModified($now);
        $announcement->setPostModifiedGmt($now);

        $this->em->persist($announcement);
        $this->em->flush();

        return [
            'target_status' => $targetStatus,
            'changed' => true,
        ];
    }

    private function resolveTargetStatus(AnnouncementModerationDecision $decision): string
    {
        if ($decision->getOutcome() === 'publish') {
            return 'publish';
        }

        if ($decision->getOutcome() === 'reject') {
            return 'trash';
        }

        return 'moderation';
    }
}
