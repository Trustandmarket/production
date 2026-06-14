<?php

namespace App\Service\AnnouncementModeration;

use App\Entity\WpPosts;
use Doctrine\ORM\EntityManagerInterface;

class AnnouncementModerationJobManager
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private EntityManagerInterface $em,
        private AnnouncementModerationContextBuilder $contextBuilder
    ) {
    }

    public function enqueueForModeration(int $announcementId, int $userId, string $sourceTransition): int
    {
        $announcement = $this->em->getRepository(WpPosts::class)->find($announcementId);

        if (!$announcement instanceof WpPosts) {
            throw new \RuntimeException(sprintf('Annonce %d introuvable.', $announcementId));
        }

        $this->cancelActiveJobsForAnnouncement($announcementId);

        $payloadSnapshot = $this->buildPayloadSnapshot($announcementId);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->em->getConnection()->insert('announcement_ai_moderation_jobs', [
            'announcement_id' => $announcementId,
            'user_id' => $userId,
            'post_status_snapshot' => (string) ($announcement->getPostStatus() ?? 'moderation'),
            'source_transition' => $sourceTransition,
            'status' => self::STATUS_PENDING,
            'attempt_count' => 0,
            'payload_snapshot' => $payloadSnapshot,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->em->getConnection()->lastInsertId();
    }

    public function cancelActiveJobsForAnnouncement(int $announcementId): int
    {
        return $this->em->getConnection()->executeStatement(
            'UPDATE announcement_ai_moderation_jobs
                SET status = :cancelled, updated_at = :updatedAt
              WHERE announcement_id = :announcementId
                AND status IN (:pending, :processing)',
            [
                'cancelled' => self::STATUS_CANCELLED,
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'announcementId' => $announcementId,
                'pending' => self::STATUS_PENDING,
                'processing' => self::STATUS_PROCESSING,
            ]
        );
    }

    private function buildPayloadSnapshot(int $announcementId): ?string
    {
        try {
            $snapshot = json_encode($this->contextBuilder->buildForAnnouncement($announcementId)->toArray());

            return $snapshot === false ? null : $snapshot;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
