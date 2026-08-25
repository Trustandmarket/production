<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Annonces\Annonces\WpPostsCrudController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AnnouncementAiModerationBackofficeController extends AbstractController
{
    private const JOB_STATUS_PENDING = 'pending';
    private const JOB_STATUS_PROCESSING = 'processing';
    private const JOB_STATUS_APPROVED = 'approved';
    private const JOB_STATUS_REJECTED = 'rejected';
    private const JOB_STATUS_FAILED = 'failed';
    private const JOB_STATUS_CANCELLED = 'cancelled';

    private const RETRYABLE_JOB_STATUSES = [
        self::JOB_STATUS_FAILED,
    ];

    private const JOB_STATUSES = [
        self::JOB_STATUS_PENDING,
        self::JOB_STATUS_PROCESSING,
        self::JOB_STATUS_APPROVED,
        self::JOB_STATUS_REJECTED,
        self::JOB_STATUS_FAILED,
        self::JOB_STATUS_CANCELLED,
    ];

    private const DECISION_SOURCES = [
        'rules_only',
        'rules_and_ai',
        'technical_failure',
    ];

    private const SORT_MAP = [
        'id' => 'j.id',
        'announcement_id' => 'j.announcement_id',
        'user_id' => 'j.user_id',
        'status' => 'j.status',
        'decision_source' => 'j.decision_source',
        'decision_code' => 'j.decision_code',
        'attempt_count' => 'j.attempt_count',
        'ai_confidence' => 'j.ai_confidence',
        'created_at' => 'j.created_at',
        'updated_at' => 'j.updated_at',
        'processed_at' => 'j.processed_at',
    ];

    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    #[Route('/bo/announcement-ai-jobs/stats', name: 'bo_announcement_ai_jobs_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        try {
            $statusFilter = $this->parseCsvFilter((string) $request->query->get('status', ''), self::JOB_STATUSES, 'status');
            $decisionSource = $this->parseDecisionSourceFilter((string) $request->query->get('decision_source', ''));
            $from = $this->parseDateFilter((string) $request->query->get('from', ''), false);
            $to = $this->parseDateFilter((string) $request->query->get('to', ''), true);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        $conn = $this->em->getConnection();
        [$whereSql, $params] = $this->buildJobFilters([
            'statuses' => $statusFilter,
            'decision_source' => $decisionSource,
            'from' => $from,
            'to' => $to,
        ]);

        $totalJobs = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM announcement_ai_moderation_jobs j WHERE {$whereSql}",
            $params
        );

        $byStatus = array_fill_keys(self::JOB_STATUSES, 0);
        $statusRows = $conn->fetchAllAssociative(
            "SELECT j.status, COUNT(*) AS total
             FROM announcement_ai_moderation_jobs j
             WHERE {$whereSql}
             GROUP BY j.status",
            $params
        );

        foreach ($statusRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $byStatus)) {
                $byStatus[$status] = (int) ($row['total'] ?? 0);
            }
        }

        $avgParams = $params;
        $avgParams['status_approved'] = self::JOB_STATUS_APPROVED;
        $avgParams['status_rejected'] = self::JOB_STATUS_REJECTED;
        $avgParams['status_failed'] = self::JOB_STATUS_FAILED;
        $avgSecondsRaw = $conn->fetchOne(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, j.created_at, j.processed_at))
             FROM announcement_ai_moderation_jobs j
             WHERE {$whereSql}
               AND j.processed_at IS NOT NULL
               AND j.status IN (:status_approved, :status_rejected, :status_failed)",
            $avgParams
        );

        $autoPublishCount = (int) $conn->fetchOne(
            "SELECT COUNT(*)
             FROM announcement_ai_moderation_jobs j
             WHERE {$whereSql}
               AND j.decision_code = :decision_code",
            $params + ['decision_code' => 'auto_publish']
        );

        $rejectedCount = (int) $conn->fetchOne(
            "SELECT COUNT(*)
             FROM announcement_ai_moderation_jobs j
             WHERE {$whereSql}
               AND j.status = :rejected",
            $params + ['rejected' => self::JOB_STATUS_REJECTED]
        );

        $topErrorsRows = $conn->fetchAllAssociative(
            "SELECT j.last_error, COUNT(*) AS occurrences
             FROM announcement_ai_moderation_jobs j
             WHERE {$whereSql}
               AND j.last_error IS NOT NULL
               AND TRIM(j.last_error) <> ''
             GROUP BY j.last_error
             ORDER BY occurrences DESC
             LIMIT 5",
            $params
        );

        $topErrors = [];
        foreach ($topErrorsRows as $row) {
            $message = trim((string) ($row['last_error'] ?? ''));
            if ($message === '') {
                continue;
            }

            $topErrors[] = [
                'message' => $this->shorten($message, 280),
                'occurrences' => (int) ($row['occurrences'] ?? 0),
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'filters' => [
                'status' => $statusFilter,
                'decision_source' => $decisionSource,
                'from' => $from,
                'to' => $to,
            ],
            'stats' => [
                'total_jobs' => $totalJobs,
                'by_status' => $byStatus,
                'auto_publish_count' => $autoPublishCount,
                'rejected_count' => $rejectedCount,
                'avg_processing_seconds' => $avgSecondsRaw !== null ? round((float) $avgSecondsRaw, 2) : null,
                'top_errors' => $topErrors,
            ],
        ]);
    }

    #[Route('/bo/announcement-ai-jobs', name: 'bo_announcement_ai_jobs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        try {
            $statusFilter = $this->parseCsvFilter((string) $request->query->get('status', ''), self::JOB_STATUSES, 'status');
            $decisionSource = $this->parseDecisionSourceFilter((string) $request->query->get('decision_source', ''));
            $announcementId = $this->parsePositiveIntFilter((string) $request->query->get('announcement_id', ''), 'announcement_id');
            $userId = $this->parsePositiveIntFilter((string) $request->query->get('user_id', ''), 'user_id');
            $hasError = $this->parseBooleanFilter((string) $request->query->get('has_error', ''), 'has_error');
            $from = $this->parseDateFilter((string) $request->query->get('from', ''), false);
            $to = $this->parseDateFilter((string) $request->query->get('to', ''), true);
            $page = $this->parsePage((string) $request->query->get('page', '1'));
            $perPage = $this->parsePerPage((string) $request->query->get('per_page', (string) self::DEFAULT_PER_PAGE));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        $sortKey = strtolower(trim((string) $request->query->get('sort', 'created_at')));
        $sortColumn = self::SORT_MAP[$sortKey] ?? self::SORT_MAP['created_at'];
        $order = strtoupper(trim((string) $request->query->get('order', 'DESC')));
        $sortOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        $conn = $this->em->getConnection();
        [$whereSql, $params] = $this->buildJobFilters([
            'statuses' => $statusFilter,
            'decision_source' => $decisionSource,
            'announcement_id' => $announcementId,
            'user_id' => $userId,
            'has_error' => $hasError,
            'from' => $from,
            'to' => $to,
        ]);

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM announcement_ai_moderation_jobs j WHERE {$whereSql}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = $conn->fetchAllAssociative(
            "SELECT j.id, j.announcement_id, j.user_id, u.display_name AS user_display_name, u.email_canonical AS user_email,
                    p.post_title AS announcement_title, p.post_name AS announcement_slug, j.post_status_snapshot, j.source_transition,
                    j.status, j.attempt_count, j.decision_source, j.decision_code, j.decision_summary, j.ai_model, j.ai_confidence,
                    j.hard_rules_pass, j.ai_pass, j.last_error, j.processed_at, j.created_at, j.updated_at
             FROM announcement_ai_moderation_jobs j
             LEFT JOIN wp_users u ON u.id = j.user_id
             LEFT JOIN wp_posts p ON p.id = j.announcement_id
             WHERE {$whereSql}
             ORDER BY {$sortColumn} {$sortOrder}, j.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $locale = $this->resolveAdminLocale($request->getLocale());
        $items = [];

        foreach ($rows as $row) {
            $jobId = (int) $row['id'];
            $announcementIdValue = (int) $row['announcement_id'];
            $status = (string) $row['status'];
            $slug = $row['announcement_slug'] !== null ? (string) $row['announcement_slug'] : '';

            $items[] = [
                'id' => $jobId,
                'announcement_id' => $announcementIdValue,
                'announcement' => [
                    'id' => $announcementIdValue,
                    'title' => $row['announcement_title'] !== null ? (string) $row['announcement_title'] : null,
                    'slug' => $slug !== '' ? $slug : null,
                    'admin_edit_url' => $this->buildAdminAnnouncementEditUrl($announcementIdValue, $locale),
                    'front_detail_url' => $this->buildFrontAnnouncementUrl($slug),
                ],
                'user' => [
                    'id' => (int) $row['user_id'],
                    'display_name' => $row['user_display_name'] !== null ? (string) $row['user_display_name'] : null,
                    'email' => $row['user_email'] !== null ? (string) $row['user_email'] : null,
                    'admin_detail_url' => $this->buildAdminUserDetailUrl((int) $row['user_id'], $locale),
                ],
                'post_status_snapshot' => (string) $row['post_status_snapshot'],
                'source_transition' => (string) $row['source_transition'],
                'status' => $status,
                'attempt_count' => (int) $row['attempt_count'],
                'decision_source' => $row['decision_source'] !== null ? (string) $row['decision_source'] : null,
                'decision_code' => $row['decision_code'] !== null ? (string) $row['decision_code'] : null,
                'decision_summary' => $row['decision_summary'] !== null ? (string) $row['decision_summary'] : null,
                'ai_model' => $row['ai_model'] !== null ? (string) $row['ai_model'] : null,
                'ai_confidence' => $row['ai_confidence'] !== null ? (float) $row['ai_confidence'] : null,
                'hard_rules_pass' => $row['hard_rules_pass'] !== null ? (bool) $row['hard_rules_pass'] : null,
                'ai_pass' => $row['ai_pass'] !== null ? (bool) $row['ai_pass'] : null,
                'last_error' => $row['last_error'],
                'processed_at' => $row['processed_at'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'can_retry' => $this->canRetryStatus($status),
                'retry_url' => $this->buildBackofficeJobRetryUrl($jobId),
                'detail_url' => $this->buildBackofficeJobDetailUrl($jobId),
            ];
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        return new JsonResponse([
            'ok' => true,
            'filters' => [
                'status' => $statusFilter,
                'decision_source' => $decisionSource,
                'announcement_id' => $announcementId,
                'user_id' => $userId,
                'has_error' => $hasError,
                'from' => $from,
                'to' => $to,
            ],
            'sort' => [
                'by' => $sortKey,
                'order' => $sortOrder,
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'items' => $items,
        ]);
    }

    #[Route('/bo/announcement-ai-jobs/{id<\d+>}', name: 'bo_announcement_ai_jobs_detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        $conn = $this->em->getConnection();
        $job = $conn->fetchAssociative(
            'SELECT j.id, j.announcement_id, j.user_id, u.display_name AS user_display_name, u.email_canonical AS user_email,
                    p.post_title AS announcement_title, p.post_name AS announcement_slug, p.post_status AS announcement_post_status,
                    j.post_status_snapshot, j.source_transition, j.status, j.attempt_count, j.decision_source, j.decision_code,
                    j.decision_summary, j.ai_model, j.ai_confidence, j.payload_snapshot, j.hard_rules_pass, j.ai_pass,
                    j.last_error, j.processed_at, j.created_at, j.updated_at
             FROM announcement_ai_moderation_jobs j
             LEFT JOIN wp_users u ON u.id = j.user_id
             LEFT JOIN wp_posts p ON p.id = j.announcement_id
             WHERE j.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$job) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Job introuvable.',
            ], 404);
        }

        $checksRows = $conn->fetchAllAssociative(
            'SELECT id, criterion_code, criterion_label, source_type, result, score, reason, raw_value, created_at, updated_at
             FROM announcement_ai_moderation_checks
             WHERE moderation_job_id = :job_id
             ORDER BY id ASC',
            ['job_id' => $id]
        );

        $checksSummary = [
            'pass' => 0,
            'fail' => 0,
            'warning' => 0,
        ];

        $checks = [];
        foreach ($checksRows as $row) {
            $result = (string) ($row['result'] ?? '');
            if (array_key_exists($result, $checksSummary)) {
                $checksSummary[$result]++;
            }

            $checks[] = [
                'id' => (int) $row['id'],
                'criterion_code' => (string) $row['criterion_code'],
                'criterion_label' => (string) $row['criterion_label'],
                'source_type' => (string) $row['source_type'],
                'result' => $result,
                'score' => $row['score'] !== null ? (float) $row['score'] : null,
                'reason' => $row['reason'] !== null ? (string) $row['reason'] : null,
                'raw_value' => $this->decodeStoredValue($row['raw_value'] ?? null),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        $locale = $this->resolveAdminLocale($request->getLocale());
        $slug = $job['announcement_slug'] !== null ? (string) $job['announcement_slug'] : '';

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => (int) $job['id'],
                'announcement_id' => (int) $job['announcement_id'],
                'announcement' => [
                    'id' => (int) $job['announcement_id'],
                    'title' => $job['announcement_title'] !== null ? (string) $job['announcement_title'] : null,
                    'slug' => $slug !== '' ? $slug : null,
                    'post_status' => $job['announcement_post_status'] !== null ? (string) $job['announcement_post_status'] : null,
                    'admin_edit_url' => $this->buildAdminAnnouncementEditUrl((int) $job['announcement_id'], $locale),
                    'front_detail_url' => $this->buildFrontAnnouncementUrl($slug),
                ],
                'user' => [
                    'id' => (int) $job['user_id'],
                    'display_name' => $job['user_display_name'] !== null ? (string) $job['user_display_name'] : null,
                    'email' => $job['user_email'] !== null ? (string) $job['user_email'] : null,
                    'admin_detail_url' => $this->buildAdminUserDetailUrl((int) $job['user_id'], $locale),
                ],
                'post_status_snapshot' => (string) $job['post_status_snapshot'],
                'source_transition' => (string) $job['source_transition'],
                'status' => (string) $job['status'],
                'attempt_count' => (int) $job['attempt_count'],
                'decision_source' => $job['decision_source'] !== null ? (string) $job['decision_source'] : null,
                'decision_code' => $job['decision_code'] !== null ? (string) $job['decision_code'] : null,
                'decision_summary' => $job['decision_summary'] !== null ? (string) $job['decision_summary'] : null,
                'ai_model' => $job['ai_model'] !== null ? (string) $job['ai_model'] : null,
                'ai_confidence' => $job['ai_confidence'] !== null ? (float) $job['ai_confidence'] : null,
                'hard_rules_pass' => $job['hard_rules_pass'] !== null ? (bool) $job['hard_rules_pass'] : null,
                'ai_pass' => $job['ai_pass'] !== null ? (bool) $job['ai_pass'] : null,
                'payload_snapshot' => $this->decodeStoredValue($job['payload_snapshot'] ?? null),
                'last_error' => $job['last_error'],
                'processed_at' => $job['processed_at'],
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
                'can_retry' => $this->canRetryStatus((string) $job['status']),
                'retry_url' => $this->buildBackofficeJobRetryUrl((int) $job['id']),
            ],
            'checks' => $checks,
            'checks_summary' => $checksSummary,
        ]);
    }

    #[Route('/bo/announcement-ai-jobs/{id<\d+>}/retry', name: 'bo_announcement_ai_jobs_retry', methods: ['POST'])]
    public function retry(int $id): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        $conn = $this->em->getConnection();
        $sourceJob = $conn->fetchAssociative(
            'SELECT id, status
             FROM announcement_ai_moderation_jobs
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$sourceJob) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Job introuvable.',
            ], 404);
        }

        $sourceStatus = (string) ($sourceJob['status'] ?? '');
        if (!$this->canRetryStatus($sourceStatus)) {
            return new JsonResponse([
                'ok' => false,
                'error' => sprintf('Le statut %s ne peut pas etre relance.', $sourceStatus),
            ], 409);
        }

        $conn->beginTransaction();

        try {
            $conn->delete('announcement_ai_moderation_checks', [
                'moderation_job_id' => $id,
            ]);

            $conn->update('announcement_ai_moderation_jobs', [
                'status' => self::JOB_STATUS_PENDING,
                'decision_source' => null,
                'decision_code' => null,
                'decision_summary' => null,
                'ai_model' => null,
                'ai_confidence' => null,
                'hard_rules_pass' => null,
                'ai_pass' => null,
                'last_error' => null,
                'processed_at' => null,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], [
                'id' => $id,
            ]);

            $conn->commit();
        } catch (\Throwable $exception) {
            $conn->rollBack();

            return new JsonResponse([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => $id,
                'status' => self::JOB_STATUS_PENDING,
            ],
        ]);
    }

    private function requireBackofficeAccess(): ?JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Utilisateur non authentifie.',
            ], 401);
        }

        if (!$this->hasBackofficeRole($user)) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Acces refuse.',
            ], 403);
        }

        return null;
    }

    private function hasBackofficeRole(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_SUPER_ADMIN', $roles, true)
            || in_array('ROLE_COMMERCE', $roles, true);
    }

    private function parseDecisionSourceFilter(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (!in_array($value, self::DECISION_SOURCES, true)) {
            throw new \InvalidArgumentException('decision_source invalide.');
        }

        return $value;
    }

    /**
     * @return string[]
     */
    private function parseCsvFilter(string $raw, array $allowedValues, string $fieldName): array
    {
        $value = trim($raw);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $value) ?: [];
        $values = [];

        foreach ($parts as $part) {
            $item = trim($part);
            if ($item === '') {
                continue;
            }

            if (!in_array($item, $allowedValues, true)) {
                throw new \InvalidArgumentException(sprintf('%s invalide: %s', $fieldName, $item));
            }

            $values[] = $item;
        }

        return array_values(array_unique($values));
    }

    private function parsePositiveIntFilter(string $raw, string $fieldName): ?int
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }
        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException(sprintf('%s doit etre un entier positif.', $fieldName));
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new \InvalidArgumentException(sprintf('%s doit etre superieur a zero.', $fieldName));
        }

        return $intValue;
    }

    private function parseBooleanFilter(string $raw, string $fieldName): ?bool
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }

        if (in_array($value, ['1', 'true', 'yes', 'oui'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'non'], true)) {
            return false;
        }

        throw new \InvalidArgumentException(sprintf('%s invalide. Utiliser true/false ou 1/0.', $fieldName));
    }

    private function parseDateFilter(string $raw, bool $endOfDay): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $suffix = $endOfDay ? ' 23:59:59' : ' 00:00:00';
                $date = new \DateTimeImmutable($value . $suffix);
            } else {
                $date = new \DateTimeImmutable($value);
                if ($endOfDay && strlen($value) <= 10) {
                    $date = $date->setTime(23, 59, 59);
                }
            }
        } catch (\Throwable) {
            throw new \InvalidArgumentException(sprintf('Date invalide: %s', $value));
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function parsePage(string $raw): int
    {
        $value = trim($raw);
        if ($value === '') {
            return 1;
        }
        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('page doit etre un entier positif.');
        }

        return max(1, (int) $value);
    }

    private function parsePerPage(string $raw): int
    {
        $value = trim($raw);
        if ($value === '') {
            return self::DEFAULT_PER_PAGE;
        }
        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('per_page doit etre un entier positif.');
        }

        $perPage = (int) $value;
        if ($perPage <= 0) {
            throw new \InvalidArgumentException('per_page doit etre superieur a zero.');
        }

        return min(self::MAX_PER_PAGE, $perPage);
    }

    private function buildJobFilters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $statuses = $filters['statuses'] ?? [];
        if (is_array($statuses) && !empty($statuses)) {
            $placeholders = [];
            foreach ($statuses as $index => $status) {
                $key = 'status_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $status;
            }
            $where[] = 'j.status IN (' . implode(', ', $placeholders) . ')';
        }

        $decisionSource = $filters['decision_source'] ?? null;
        if ($decisionSource !== null) {
            $where[] = 'j.decision_source = :decision_source_filter';
            $params['decision_source_filter'] = $decisionSource;
        }

        $announcementId = $filters['announcement_id'] ?? null;
        if ($announcementId !== null) {
            $where[] = 'j.announcement_id = :announcement_id';
            $params['announcement_id'] = $announcementId;
        }

        $userId = $filters['user_id'] ?? null;
        if ($userId !== null) {
            $where[] = 'j.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if (array_key_exists('has_error', $filters) && $filters['has_error'] !== null) {
            if ($filters['has_error'] === true) {
                $where[] = "(j.last_error IS NOT NULL AND TRIM(j.last_error) <> '')";
            } else {
                $where[] = "(j.last_error IS NULL OR TRIM(j.last_error) = '')";
            }
        }

        $from = $filters['from'] ?? null;
        if ($from !== null) {
            $where[] = 'j.created_at >= :from_date';
            $params['from_date'] = $from;
        }

        $to = $filters['to'] ?? null;
        if ($to !== null) {
            $where[] = 'j.created_at <= :to_date';
            $params['to_date'] = $to;
        }

        return [implode(' AND ', $where), $params];
    }

    private function decodeStoredValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($this->sanitizeUtf8String($value));
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->sanitizeUtf8Value($decoded);
        }

        return $this->sanitizeUtf8String($value);
    }

    private function buildAdminAnnouncementEditUrl(int $announcementId, string $locale): string
    {
        if ($announcementId <= 0) {
            return '';
        }

        try {
            $urlGenerator = clone $this->adminUrlGenerator;

            return $urlGenerator
                ->setRoute('admin', ['_locale' => $locale])
                ->setController(WpPostsCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($announcementId)
                ->generateUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildAdminUserDetailUrl(int $userId, string $locale): string
    {
        if ($userId <= 0) {
            return '';
        }

        try {
            $urlGenerator = clone $this->adminUrlGenerator;

            return $urlGenerator
                ->setRoute('admin', ['_locale' => $locale])
                ->setController(UserCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($userId)
                ->generateUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildBackofficeJobDetailUrl(int $jobId): string
    {
        if ($jobId <= 0) {
            return '';
        }

        try {
            return $this->generateUrl('bo_announcement_ai_jobs_detail', [
                'id' => $jobId,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildBackofficeJobRetryUrl(int $jobId): string
    {
        if ($jobId <= 0) {
            return '';
        }

        try {
            return $this->generateUrl('bo_announcement_ai_jobs_retry', [
                'id' => $jobId,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildFrontAnnouncementUrl(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        return sprintf('/fr/annonces/details/%s', rawurlencode($slug));
    }

    private function resolveAdminLocale(?string $locale): string
    {
        $value = strtolower(trim((string) $locale));
        if (str_starts_with($value, 'en')) {
            return 'en';
        }

        return 'fr';
    }

    private function canRetryStatus(string $status): bool
    {
        return in_array($status, self::RETRYABLE_JOB_STATUSES, true);
    }

    private function shorten(string $value, int $maxLength): string
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

    private function sanitizeUtf8Value(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeUtf8String($value);
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $cleanKey = is_string($key) ? $this->sanitizeUtf8String($key) : $key;
                $clean[$cleanKey] = $this->sanitizeUtf8Value($item);
            }

            return $clean;
        }

        return $value;
    }

    private function sanitizeUtf8String(string $value): string
    {
        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }
}
