<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProfileAiEnrichmentBackofficeController extends AbstractController
{
    private const JOB_STATUS_PENDING = 'pending';
    private const JOB_STATUS_PROCESSING = 'processing';
    private const JOB_STATUS_AWAITING_USER = 'awaiting_user';
    private const JOB_STATUS_APPLYING = 'applying';
    private const JOB_STATUS_SUCCESS = 'success';
    private const JOB_STATUS_FAILED = 'failed';
    private const RETRYABLE_JOB_STATUSES = [
        self::JOB_STATUS_FAILED,
    ];

    private const JOB_STATUSES = [
        self::JOB_STATUS_PENDING,
        self::JOB_STATUS_PROCESSING,
        self::JOB_STATUS_AWAITING_USER,
        self::JOB_STATUS_APPLYING,
        self::JOB_STATUS_SUCCESS,
        self::JOB_STATUS_FAILED,
    ];

    private const INPUT_TYPES = [
        'studio_name',
        'website',
    ];

    private const EXCLUDED_SUGGESTION_FIELDS = ['photos', 'videos', 'avatar_url'];

    private const SORT_MAP = [
        'id' => 'j.id',
        'profile_id' => 'j.profile_id',
        'status' => 'j.status',
        'input_type' => 'j.input_type',
        'attempt_count' => 'j.attempt_count',
        'confidence_global' => 'j.confidence_global',
        'created_at' => 'j.created_at',
        'updated_at' => 'j.updated_at',
    ];

    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator
    )
    {
    }

    #[Route('/bo/enrichment-jobs/stats', name: 'bo_profile_ai_enrichment_jobs_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        try {
            $inputType = $this->parseInputTypeFilter((string) $request->query->get('input_type', ''));
            $from = $this->parseDateFilter((string) $request->query->get('from', ''), false);
            $to = $this->parseDateFilter((string) $request->query->get('to', ''), true);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        $conn = $this->em->getConnection();
        [$whereSql, $params] = $this->buildJobFilters([
            'input_type' => $inputType,
            'from' => $from,
            'to' => $to,
        ]);

        $totalJobs = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM profile_ai_enrichment_jobs j WHERE {$whereSql}",
            $params
        );

        $byStatus = array_fill_keys(self::JOB_STATUSES, 0);
        $statusRows = $conn->fetchAllAssociative(
            "SELECT j.status, COUNT(*) AS total
             FROM profile_ai_enrichment_jobs j
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

        $successRatePercent = $totalJobs > 0
            ? round(($byStatus[self::JOB_STATUS_SUCCESS] / $totalJobs) * 100, 2)
            : 0.0;

        $avgParams = $params;
        $avgParams['status_awaiting'] = self::JOB_STATUS_AWAITING_USER;
        $avgParams['status_failed'] = self::JOB_STATUS_FAILED;
        $avgSecondsRaw = $conn->fetchOne(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, j.created_at, j.updated_at))
             FROM profile_ai_enrichment_jobs j
             WHERE {$whereSql}
               AND j.updated_at IS NOT NULL
               AND j.status IN (:status_awaiting, :status_failed)",
            $avgParams
        );

        $avgProcessingSeconds = $avgSecondsRaw !== null ? round((float) $avgSecondsRaw, 2) : null;

        $topErrorsRows = $conn->fetchAllAssociative(
            "SELECT j.last_error, COUNT(*) AS occurrences
             FROM profile_ai_enrichment_jobs j
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
                'input_type' => $inputType,
                'from' => $from,
                'to' => $to,
            ],
            'stats' => [
                'total_jobs' => $totalJobs,
                'by_status' => $byStatus,
                'success_rate_percent' => $successRatePercent,
                'avg_processing_seconds' => $avgProcessingSeconds,
                'top_errors' => $topErrors,
            ],
        ]);
    }

    #[Route('/bo/enrichment-jobs', name: 'bo_profile_ai_enrichment_jobs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        try {
            $statusFilter = $this->parseCsvFilter((string) $request->query->get('status', ''), self::JOB_STATUSES, 'status');
            $inputType = $this->parseInputTypeFilter((string) $request->query->get('input_type', ''));
            $profileId = $this->parsePositiveIntFilter((string) $request->query->get('profile_id', ''), 'profile_id');
            $hasError = $this->parseBooleanFilter((string) $request->query->get('has_error', ''), 'has_error');
            $minConfidence = $this->parseFloatFilter((string) $request->query->get('min_confidence', ''), 'min_confidence');
            $maxConfidence = $this->parseFloatFilter((string) $request->query->get('max_confidence', ''), 'max_confidence');
            $from = $this->parseDateFilter((string) $request->query->get('from', ''), false);
            $to = $this->parseDateFilter((string) $request->query->get('to', ''), true);
            $page = $this->parsePage((string) $request->query->get('page', '1'));
            $perPage = $this->parsePerPage((string) $request->query->get('per_page', (string) self::DEFAULT_PER_PAGE));
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        if ($minConfidence !== null && $maxConfidence !== null && $minConfidence > $maxConfidence) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'min_confidence doit etre inferieur ou egal a max_confidence.',
            ], 400);
        }

        $sortKey = strtolower(trim((string) $request->query->get('sort', 'created_at')));
        $sortColumn = self::SORT_MAP[$sortKey] ?? self::SORT_MAP['created_at'];
        $order = strtoupper(trim((string) $request->query->get('order', 'DESC')));
        $sortOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        $conn = $this->em->getConnection();
        [$whereSql, $params] = $this->buildJobFilters([
            'statuses' => $statusFilter,
            'input_type' => $inputType,
            'profile_id' => $profileId,
            'has_error' => $hasError,
            'min_confidence' => $minConfidence,
            'max_confidence' => $maxConfidence,
            'from' => $from,
            'to' => $to,
        ]);

        $total = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM profile_ai_enrichment_jobs j WHERE {$whereSql}",
            $params
        );

        $offset = ($page - 1) * $perPage;
        $rows = $conn->fetchAllAssociative(
            "SELECT j.id, j.profile_id, u.display_name AS profile_display_name, j.input_type, j.input_value, j.input_region, j.status, j.attempt_count, j.last_error,
                    j.prompt_version, j.confidence_global, j.active_profile_id, j.created_at, j.updated_at
             FROM profile_ai_enrichment_jobs j
             LEFT JOIN wp_users u ON u.id = j.profile_id
             WHERE {$whereSql}
             ORDER BY {$sortColumn} {$sortOrder}, j.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $locale = $this->resolveAdminLocale($request->getLocale());
        $items = [];
        foreach ($rows as $row) {
            $itemProfileId = (int) $row['profile_id'];
            $jobId = (int) $row['id'];
            $itemStatus = (string) $row['status'];
            $items[] = [
                'id' => $jobId,
                'profile_id' => $itemProfileId,
                'profile' => [
                    'id' => $itemProfileId,
                    'display_name' => $row['profile_display_name'] !== null ? (string) $row['profile_display_name'] : null,
                    'admin_detail_url' => $this->buildAdminUserDetailUrl($itemProfileId, $locale),
                ],
                'backoffice_detail_url' => $this->buildBackofficeJobDetailUrl($jobId, $locale),
                'retry_url' => $this->buildBackofficeJobRetryUrl($jobId),
                'input_type' => (string) $row['input_type'],
                'input_value' => (string) $row['input_value'],
                'input_region' => $row['input_region'] !== null ? (string) $row['input_region'] : null,
                'status' => $itemStatus,
                'can_retry' => $this->canRetryStatus($itemStatus),
                'attempt_count' => (int) $row['attempt_count'],
                'last_error' => $row['last_error'],
                'prompt_version' => $row['prompt_version'],
                'confidence_global' => $row['confidence_global'] !== null ? (float) $row['confidence_global'] : null,
                'active_profile_id' => $row['active_profile_id'] !== null ? (int) $row['active_profile_id'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        return new JsonResponse([
            'ok' => true,
            'filters' => [
                'status' => $statusFilter,
                'input_type' => $inputType,
                'profile_id' => $profileId,
                'has_error' => $hasError,
                'min_confidence' => $minConfidence,
                'max_confidence' => $maxConfidence,
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

    #[Route('/bo/enrichment-jobs/{id<\d+>}', name: 'bo_profile_ai_enrichment_jobs_detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        $conn = $this->em->getConnection();
        $job = $conn->fetchAssociative(
            'SELECT j.id, j.profile_id, u.display_name AS profile_display_name, j.input_type, j.input_value, j.input_region, j.status, j.attempt_count, j.last_error, j.prompt_version,
                    j.confidence_global, j.active_profile_id, j.created_at, j.updated_at
             FROM profile_ai_enrichment_jobs j
             LEFT JOIN wp_users u ON u.id = j.profile_id
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

        $suggestionsRows = $conn->fetchAllAssociative(
            'SELECT id, profile_id, field_name, suggested_value, confidence_score, source_type, status, final_value, created_at, updated_at
             FROM profile_ai_suggestions
             WHERE enrichment_job_id = :job_id
             ORDER BY id ASC',
            ['job_id' => $id]
        );

        $suggestionsSummary = [
            'suggested' => 0,
            'accepted' => 0,
            'edited' => 0,
            'rejected' => 0,
        ];

        $suggestions = [];
        foreach ($suggestionsRows as $row) {
            $fieldName = (string) ($row['field_name'] ?? '');
            if ($this->isExcludedSuggestionField($fieldName)) {
                continue;
            }

            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $suggestionsSummary)) {
                $suggestionsSummary[$status]++;
            }

            $suggestions[] = [
                'id' => (int) $row['id'],
                'profile_id' => (int) $row['profile_id'],
                'field_name' => $fieldName,
                'suggested_value' => $this->decodeStoredValue($row['suggested_value'] ?? null),
                'confidence_score' => $row['confidence_score'] !== null ? (float) $row['confidence_score'] : null,
                'source_type' => $row['source_type'],
                'status' => $status,
                'final_value' => $this->decodeStoredValue($row['final_value'] ?? null),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        $locale = $this->resolveAdminLocale($request->getLocale());
        $jobProfileId = (int) $job['profile_id'];

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => (int) $job['id'],
                'profile_id' => $jobProfileId,
                'profile' => [
                    'id' => $jobProfileId,
                    'display_name' => $job['profile_display_name'] !== null ? (string) $job['profile_display_name'] : null,
                    'admin_detail_url' => $this->buildAdminUserDetailUrl($jobProfileId, $locale),
                ],
                'input_type' => (string) $job['input_type'],
                'input_value' => (string) $job['input_value'],
                'input_region' => $job['input_region'] !== null ? (string) $job['input_region'] : null,
                'status' => (string) $job['status'],
                'attempt_count' => (int) $job['attempt_count'],
                'last_error' => $job['last_error'],
                'prompt_version' => $job['prompt_version'],
                'confidence_global' => $job['confidence_global'] !== null ? (float) $job['confidence_global'] : null,
                'active_profile_id' => $job['active_profile_id'] !== null ? (int) $job['active_profile_id'] : null,
                'can_retry' => $this->canRetryStatus((string) $job['status']),
                'retry_url' => $this->buildBackofficeJobRetryUrl((int) $job['id']),
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
            ],
            'suggestions' => $suggestions,
            'suggestions_summary' => $suggestionsSummary,
        ]);
    }

    #[Route('/bo/enrichment-jobs/{id<\d+>}/retry', name: 'bo_profile_ai_enrichment_jobs_retry', methods: ['POST'])]
    public function retry(int $id): JsonResponse
    {
        $accessError = $this->requireBackofficeAccess();
        if ($accessError !== null) {
            return $accessError;
        }

        $conn = $this->em->getConnection();
        $sourceJob = $conn->fetchAssociative(
            'SELECT id, profile_id, input_type, input_value, input_region, status, prompt_version
             FROM profile_ai_enrichment_jobs
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
                'error' => 'Ce job ne peut pas etre relance dans son statut actuel.',
                'status' => $sourceStatus,
                'allowed_statuses' => self::RETRYABLE_JOB_STATUSES,
            ], 409);
        }

        $profileId = (int) ($sourceJob['profile_id'] ?? 0);
        if ($profileId <= 0) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Profile invalide pour ce job.',
            ], 400);
        }

        $activeJob = $conn->fetchAssociative(
            'SELECT id, status
             FROM profile_ai_enrichment_jobs
             WHERE profile_id = :profile_id AND active_profile_id = :active_profile_id
             ORDER BY id DESC
             LIMIT 1',
            [
                'profile_id' => $profileId,
                'active_profile_id' => $profileId,
            ]
        );

        if ($activeJob) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Un job actif existe deja pour ce profil.',
                'active_job' => [
                    'id' => (int) $activeJob['id'],
                    'status' => (string) $activeJob['status'],
                ],
            ], 409);
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        try {
            $conn->insert('profile_ai_enrichment_jobs', [
                'profile_id' => $profileId,
                'input_type' => (string) $sourceJob['input_type'],
                'input_value' => (string) $sourceJob['input_value'],
                'input_region' => $sourceJob['input_region'] !== null ? (string) $sourceJob['input_region'] : null,
                'status' => self::JOB_STATUS_PENDING,
                'attempt_count' => 0,
                'last_error' => null,
                'prompt_version' => $sourceJob['prompt_version'],
                'confidence_global' => null,
                'active_profile_id' => $profileId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Un job actif existe deja pour ce profil.',
            ], 409);
        } catch (\Throwable) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Erreur technique lors de la creation du job de relance.',
            ], 500);
        }

        $newJobId = (int) $conn->lastInsertId();

        return new JsonResponse([
            'ok' => true,
            'source_job' => [
                'id' => (int) $sourceJob['id'],
                'status' => $sourceStatus,
            ],
            'job' => [
                'id' => $newJobId,
                'profile_id' => $profileId,
                'input_type' => (string) $sourceJob['input_type'],
                'input_value' => (string) $sourceJob['input_value'],
                'input_region' => $sourceJob['input_region'] !== null ? (string) $sourceJob['input_region'] : null,
                'status' => self::JOB_STATUS_PENDING,
                'prompt_version' => $sourceJob['prompt_version'],
                'created_at' => $now,
            ],
        ], 201);
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

    private function parseInputTypeFilter(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (!in_array($value, self::INPUT_TYPES, true)) {
            throw new \InvalidArgumentException('input_type invalide. Valeurs autorisees: studio_name, website.');
        }

        return $value;
    }

    private function parseCsvFilter(string $raw, array $allowedValues, string $fieldName): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = explode(',', $raw);
        $values = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $allowedValues, true)) {
                throw new \InvalidArgumentException(sprintf('%s invalide: %s', $fieldName, $value));
            }
            $values[] = $value;
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

    private function parseFloatFilter(string $raw, string $fieldName): ?float
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('%s doit etre numerique.', $fieldName));
        }

        return (float) $value;
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
        $page = max(1, (int) $value);

        return $page;
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

        $inputType = $filters['input_type'] ?? null;
        if ($inputType !== null) {
            $where[] = 'j.input_type = :input_type';
            $params['input_type'] = $inputType;
        }

        $profileId = $filters['profile_id'] ?? null;
        if ($profileId !== null) {
            $where[] = 'j.profile_id = :profile_id';
            $params['profile_id'] = $profileId;
        }

        if (array_key_exists('has_error', $filters) && $filters['has_error'] !== null) {
            if ($filters['has_error'] === true) {
                $where[] = "(j.last_error IS NOT NULL AND TRIM(j.last_error) <> '')";
            } else {
                $where[] = "(j.last_error IS NULL OR TRIM(j.last_error) = '')";
            }
        }

        $minConfidence = $filters['min_confidence'] ?? null;
        if ($minConfidence !== null) {
            $where[] = 'j.confidence_global IS NOT NULL AND j.confidence_global >= :min_confidence';
            $params['min_confidence'] = $minConfidence;
        }

        $maxConfidence = $filters['max_confidence'] ?? null;
        if ($maxConfidence !== null) {
            $where[] = 'j.confidence_global IS NOT NULL AND j.confidence_global <= :max_confidence';
            $params['max_confidence'] = $maxConfidence;
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

    private function shorten(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $maxLength) {
                return $value;
            }

            return mb_substr($value, 0, $maxLength);
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }

    private function resolveAdminLocale(?string $locale): string
    {
        $value = strtolower(trim((string) $locale));
        if (str_starts_with($value, 'en')) {
            return 'en';
        }

        return 'fr';
    }

    private function buildAdminUserDetailUrl(int $profileId, string $locale): string
    {
        if ($profileId <= 0) {
            return '';
        }

        try {
            $urlGenerator = clone $this->adminUrlGenerator;

            return $urlGenerator
                ->setRoute('admin', ['_locale' => $locale])
                ->setController(UserCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($profileId)
                ->generateUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildBackofficeJobDetailUrl(int $jobId, string $locale): string
    {
        if ($jobId <= 0) {
            return '';
        }

        try {
            return $this->generateUrl('admin_ai_enrichment_job_detail', [
                '_locale' => $locale,
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
            return $this->generateUrl('bo_profile_ai_enrichment_jobs_retry', [
                'id' => $jobId,
            ]);
        } catch (\Throwable) {
            return '';
        }
    }

    private function canRetryStatus(string $status): bool
    {
        return in_array($status, self::RETRYABLE_JOB_STATUSES, true);
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

    private function isExcludedSuggestionField(string $fieldName): bool
    {
        return in_array(trim($fieldName), self::EXCLUDED_SUGGESTION_FIELDS, true);
    }
}
