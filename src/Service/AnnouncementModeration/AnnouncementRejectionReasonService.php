<?php

namespace App\Service\AnnouncementModeration;

use Doctrine\ORM\EntityManagerInterface;

class AnnouncementRejectionReasonService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * @return array{
     *     job_id: ?int,
     *     decision_code: string,
     *     decision_summary_ai: string,
     *     reasons: array<int, array{code: string, label: string, message: string}>,
     *     nb_raisons: int,
     *     raisons_rejet_html: string,
     *     raisons_rejet_text: string
     * }
     */
    public function buildForAnnouncement(int $announcementId): array
    {
        if ($announcementId <= 0) {
            return $this->emptyPayload();
        }

        try {
            $conn = $this->em->getConnection();
            $job = $conn->fetchAssociative(
                "SELECT id, decision_code, decision_summary
                 FROM announcement_ai_moderation_jobs
                 WHERE announcement_id = :announcementId
                   AND status IN ('rejected', 'failed', 'approved')
                 ORDER BY
                   CASE
                     WHEN status = 'rejected' THEN 1
                     WHEN status = 'failed' THEN 2
                     WHEN status = 'approved' THEN 3
                     ELSE 4
                   END,
                   processed_at DESC,
                   id DESC
                 LIMIT 1",
                [
                    'announcementId' => $announcementId,
                ]
            );

            if (!is_array($job) || !isset($job['id'])) {
                return $this->emptyPayload();
            }

            $jobId = (int) $job['id'];
            $rows = $conn->fetchAllAssociative(
                "SELECT criterion_code, criterion_label, reason
                 FROM announcement_ai_moderation_checks
                 WHERE moderation_job_id = :jobId
                   AND result = 'fail'
                 ORDER BY id ASC",
                [
                    'jobId' => $jobId,
                ]
            );

            $reasons = [];
            $seenMessages = [];

            foreach ($rows as $row) {
                $code = trim((string) ($row['criterion_code'] ?? ''));
                $label = trim((string) ($row['criterion_label'] ?? ''));
                $reason = trim((string) ($row['reason'] ?? ''));
                $message = $this->resolveUserMessage($code, $label, $reason);

                if ($message === '') {
                    continue;
                }

                $dedupeKey = $this->normalizeDedupeKey($message);
                if (isset($seenMessages[$dedupeKey])) {
                    continue;
                }

                $seenMessages[$dedupeKey] = true;
                $reasons[] = [
                    'code' => $code,
                    'label' => $label,
                    'message' => $message,
                ];
            }

            return [
                'job_id' => $jobId,
                'decision_code' => trim((string) ($job['decision_code'] ?? '')),
                'decision_summary_ai' => trim((string) ($job['decision_summary'] ?? '')),
                'reasons' => $reasons,
                'nb_raisons' => count($reasons),
                'raisons_rejet_html' => $this->buildHtmlList($reasons),
                'raisons_rejet_text' => $this->buildTextList($reasons),
            ];
        } catch (\Throwable) {
            return $this->emptyPayload();
        }
    }

    /**
     * @param array<int, array{code: string, label: string, message: string}> $reasons
     */
    private function buildHtmlList(array $reasons): string
    {
        if ($reasons === []) {
            return '';
        }

        $items = [];
        foreach ($reasons as $reason) {
            $items[] = '- ' . $this->escapeHtml($reason['message']);
        }

        return implode('<br>', $items);
    }

    /**
     * @param array<int, array{code: string, label: string, message: string}> $reasons
     */
    private function buildTextList(array $reasons): string
    {
        if ($reasons === []) {
            return '';
        }

        $lines = [];
        foreach ($reasons as $reason) {
            $lines[] = '- ' . $reason['message'];
        }

        return implode("\n", $lines);
    }

    private function resolveUserMessage(string $code, string $label, string $reason): string
    {
        return match ($code) {
            'has_title' => 'Le titre de l’annonce est obligatoire.',
            'title_min_length' => 'Le titre de l’annonce doit contenir au moins 30 caractères.',
            'has_description' => 'La description de l’annonce est obligatoire.',
            'description_min_length' => 'La description doit contenir au moins 300 caractères.',
            'has_main_photo' => 'Au moins une photo est requise pour publier l’annonce.',
            'price_min' => 'Le prix affiché n’est pas cohérent.',
            default => $this->normalizeFallbackMessage($label, $reason),
        };
    }

    private function normalizeFallbackMessage(string $label, string $reason): string
    {
        $preferred = trim($reason);
        if ($preferred !== '' && strtoupper($preferred) !== 'OK') {
            return rtrim($preferred, '.') . '.';
        }

        if ($label !== '') {
            return sprintf('%s doit être corrigé.', $label);
        }

        return 'Un élément de l’annonce doit être corrigé.';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normalizeDedupeKey(string $message): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($message, 'UTF-8');
        }

        return strtolower($message);
    }

    /**
     * @return array{
     *     job_id: ?int,
     *     decision_code: string,
     *     decision_summary_ai: string,
     *     reasons: array<int, array{code: string, label: string, message: string}>,
     *     nb_raisons: int,
     *     raisons_rejet_html: string,
     *     raisons_rejet_text: string
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'job_id' => null,
            'decision_code' => '',
            'decision_summary_ai' => '',
            'reasons' => [],
            'nb_raisons' => 0,
            'raisons_rejet_html' => '',
            'raisons_rejet_text' => '',
        ];
    }
}
