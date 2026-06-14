<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationCheckResult;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationDecision;
use Doctrine\DBAL\Connection;
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

    public function findNextPendingJob(?int $targetJobId = null): ?array
    {
        $conn = $this->em->getConnection();

        if ($targetJobId !== null && $targetJobId > 0) {
            $row = $conn->fetchAssociative(
                'SELECT id, announcement_id, user_id, source_transition, status, attempt_count
                 FROM announcement_ai_moderation_jobs
                 WHERE id = :id AND status = :status
                 LIMIT 1',
                [
                    'id' => $targetJobId,
                    'status' => self::STATUS_PENDING,
                ]
            );

            return $row ?: null;
        }

        $row = $conn->fetchAssociative(
            'SELECT id, announcement_id, user_id, source_transition, status, attempt_count
             FROM announcement_ai_moderation_jobs
             WHERE status = :status
             ORDER BY created_at ASC, id ASC
             LIMIT 1',
            [
                'status' => self::STATUS_PENDING,
            ]
        );

        return $row ?: null;
    }

    public function claimJob(int $jobId): bool
    {
        $affectedRows = $this->executeStatement(
            $this->em->getConnection(),
            'UPDATE announcement_ai_moderation_jobs
                SET status = :processing,
                    attempt_count = attempt_count + 1,
                    updated_at = :updatedAt,
                    last_error = NULL
              WHERE id = :id AND status = :pending',
            [
                'processing' => self::STATUS_PROCESSING,
                'updatedAt' => $this->now(),
                'id' => $jobId,
                'pending' => self::STATUS_PENDING,
            ]
        );

        return $affectedRows > 0;
    }

    public function cancelActiveJobsForAnnouncement(int $announcementId): int
    {
        return $this->executeStatement(
            $this->em->getConnection(),
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

    /**
     * @param array<int, AnnouncementModerationCheckResult> $checks
     * @param array<string, mixed> $payloadSnapshot
     */
    public function completeJob(
        int $jobId,
        AnnouncementModerationDecision $decision,
        array $checks,
        array $payloadSnapshot
    ): void {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $this->executeStatement(
                $conn,
                'DELETE FROM announcement_ai_moderation_checks WHERE moderation_job_id = :jobId',
                ['jobId' => $jobId]
            );

            foreach ($checks as $check) {
                $data = $check->toArray();

                $conn->insert('announcement_ai_moderation_checks', [
                    'moderation_job_id' => $jobId,
                    'criterion_code' => $data['criterion_code'],
                    'criterion_label' => $data['criterion_label'],
                    'source_type' => $data['source_type'],
                    'result' => $data['result'],
                    'score' => $data['score'],
                    'reason' => $data['reason'],
                    'raw_value' => $this->encodeJson($data['raw_value']),
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);
            }

            $conn->update('announcement_ai_moderation_jobs', [
                'status' => $decision->getJobStatus(),
                'decision_source' => $decision->getDecisionSource(),
                'decision_code' => $decision->getDecisionCode(),
                'decision_summary' => $this->truncate($decision->getSummary(), 255),
                'ai_model' => $this->truncate((string) $decision->getAiModel(), 100),
                'ai_confidence' => $decision->getAiConfidence(),
                'payload_snapshot' => $this->encodeJson($payloadSnapshot),
                'hard_rules_pass' => $decision->isHardRulesPass() ? 1 : 0,
                'ai_pass' => $decision->isAiPass() === null ? null : ($decision->isAiPass() ? 1 : 0),
                'last_error' => null,
                'processed_at' => $this->now(),
                'updated_at' => $this->now(),
            ], [
                'id' => $jobId,
            ]);

            $conn->commit();
        } catch (\Throwable $exception) {
            $conn->rollBack();
            throw $exception;
        }
    }

    public function markJobAsFailed(int $jobId, string $errorMessage): void
    {
        $this->em->getConnection()->update('announcement_ai_moderation_jobs', [
            'status' => 'failed',
            'decision_source' => 'technical_failure',
            'decision_code' => 'worker_exception',
            'decision_summary' => 'Erreur technique pendant le traitement du job.',
            'last_error' => $this->truncate($errorMessage, 65000),
            'processed_at' => $this->now(),
            'updated_at' => $this->now(),
        ], [
            'id' => $jobId,
        ]);
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

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function executeStatement(Connection $conn, string $sql, array $params = []): int
    {
        if (method_exists($conn, 'executeStatement')) {
            return (int) $conn->executeStatement($sql, $params);
        }

        if (method_exists($conn, 'executeUpdate')) {
            /** @phpstan-ignore-next-line */
            return (int) $conn->executeUpdate($sql, $params);
        }

        $stmt = $conn->prepare($sql);
        if (method_exists($stmt, 'executeStatement')) {
            /** @phpstan-ignore-next-line */
            return (int) $stmt->executeStatement($params);
        }
        if (method_exists($stmt, 'execute')) {
            /** @phpstan-ignore-next-line */
            $stmt->execute($params);
            return method_exists($stmt, 'rowCount') ? (int) $stmt->rowCount() : 0;
        }

        return 0;
    }

    /**
     * @param mixed $value
     */
    private function encodeJson($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
    }

    private function truncate(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $maxLength) {
                return $value;
            }

            return (string) mb_substr($value, 0, $maxLength);
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }
}
