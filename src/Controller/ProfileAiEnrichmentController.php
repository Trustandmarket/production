<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ProfileCompletionCalculator;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProfileAiEnrichmentController extends AbstractController
{
    private const JOB_STATUS_PENDING = 'pending';
    private const JOB_STATUS_AWAITING_USER = 'awaiting_user';
    private const JOB_STATUS_APPLYING = 'applying';
    private const JOB_STATUS_SUCCESS = 'success';
    private const JOB_STATUS_FAILED = 'failed';

    private const JOB_INPUT_TYPES = [
        'studio_name',
        'website',
    ];

    private const SUGGESTION_STATUS_SUGGESTED = 'suggested';
    private const SUGGESTION_STATUS_ACCEPTED = 'accepted';
    private const SUGGESTION_STATUS_EDITED = 'edited';
    private const SUGGESTION_STATUS_REJECTED = 'rejected';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServiceManager $serviceManager,
        private readonly ProfileCompletionCalculator $profileCompletionCalculator
    ) {
    }

    #[Route('/enrichment-jobs', name: 'profile_ai_enrichment_jobs_create', methods: ['POST'])]
    public function createJob(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Utilisateur non authentifie.'], 401);
        }

        $payload = $this->parseJsonPayload($request);
        if ($payload === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Payload JSON invalide.'], 400);
        }

        $inputType = trim((string) ($payload['input_type'] ?? ''));
        $inputValue = trim((string) ($payload['input_value'] ?? ''));
        $promptVersion = trim((string) ($payload['prompt_version'] ?? ''));
        if ($promptVersion === '') {
            $promptVersion = null;
        }

        if (!in_array($inputType, self::JOB_INPUT_TYPES, true)) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'input_type invalide. Valeurs autorisees: studio_name, website.',
            ], 400);
        }

        if ($inputValue === '') {
            return new JsonResponse(['ok' => false, 'error' => 'input_value est obligatoire.'], 400);
        }

        if (mb_strlen($inputValue) > 500) {
            return new JsonResponse(['ok' => false, 'error' => 'input_value est trop long (max 500 caracteres).'], 400);
        }

        $profileId = (int) $user->getId();
        $conn = $this->em->getConnection();
        $now = $this->now();

        $activeJob = $conn->fetchAssociative(
            'SELECT id, status FROM profile_ai_enrichment_jobs WHERE profile_id = :profile_id AND active_profile_id = :active_profile_id ORDER BY id DESC LIMIT 1',
            [
                'profile_id' => $profileId,
                'active_profile_id' => $profileId,
            ]
        );

        if ($activeJob) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Un job actif existe deja pour ce profil.',
                'job' => [
                    'id' => (int) $activeJob['id'],
                    'status' => (string) $activeJob['status'],
                ],
            ], 409);
        }

        $conn->insert('profile_ai_enrichment_jobs', [
            'profile_id' => $profileId,
            'input_type' => $inputType,
            'input_value' => $inputValue,
            'status' => self::JOB_STATUS_PENDING,
            'attempt_count' => 0,
            'last_error' => null,
            'prompt_version' => $promptVersion,
            'confidence_global' => null,
            'active_profile_id' => $profileId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $jobId = (int) $conn->lastInsertId();

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => $jobId,
                'profile_id' => $profileId,
                'input_type' => $inputType,
                'input_value' => $inputValue,
                'status' => self::JOB_STATUS_PENDING,
                'prompt_version' => $promptVersion,
                'created_at' => $now,
            ],
        ], 201);
    }

    #[Route('/enrichment-jobs/{id<\d+>}', name: 'profile_ai_enrichment_jobs_get', methods: ['GET'])]
    public function getJob(int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Utilisateur non authentifie.'], 401);
        }

        $profileId = (int) $user->getId();
        $conn = $this->em->getConnection();

        $job = $conn->fetchAssociative(
            'SELECT id, profile_id, input_type, input_value, status, attempt_count, last_error, prompt_version, confidence_global, created_at, updated_at
             FROM profile_ai_enrichment_jobs
             WHERE id = :id AND profile_id = :profile_id
             LIMIT 1',
            [
                'id' => $id,
                'profile_id' => $profileId,
            ]
        );

        if (!$job) {
            return new JsonResponse(['ok' => false, 'error' => 'Job introuvable.'], 404);
        }

        $suggestions = $conn->fetchAllAssociative(
            'SELECT id, field_name, suggested_value, confidence_score, source_type, status, final_value, created_at, updated_at
             FROM profile_ai_suggestions
             WHERE enrichment_job_id = :job_id AND profile_id = :profile_id
             ORDER BY id ASC',
            [
                'job_id' => $id,
                'profile_id' => $profileId,
            ]
        );

        $summary = [
            self::SUGGESTION_STATUS_SUGGESTED => 0,
            self::SUGGESTION_STATUS_ACCEPTED => 0,
            self::SUGGESTION_STATUS_EDITED => 0,
            self::SUGGESTION_STATUS_REJECTED => 0,
        ];

        $payloadSuggestions = [];
        foreach ($suggestions as $suggestion) {
            $status = (string) $suggestion['status'];
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            $payloadSuggestions[] = [
                'id' => (int) $suggestion['id'],
                'field_name' => (string) $suggestion['field_name'],
                'suggested_value' => $this->decodeStoredValue($suggestion['suggested_value'] ?? null),
                'confidence_score' => $suggestion['confidence_score'] !== null ? (float) $suggestion['confidence_score'] : null,
                'source_type' => $suggestion['source_type'],
                'status' => $status,
                'final_value' => $this->decodeStoredValue($suggestion['final_value'] ?? null),
                'created_at' => $suggestion['created_at'],
                'updated_at' => $suggestion['updated_at'],
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => (int) $job['id'],
                'profile_id' => (int) $job['profile_id'],
                'input_type' => (string) $job['input_type'],
                'input_value' => (string) $job['input_value'],
                'status' => (string) $job['status'],
                'attempt_count' => (int) $job['attempt_count'],
                'last_error' => $job['last_error'],
                'prompt_version' => $job['prompt_version'],
                'confidence_global' => $job['confidence_global'] !== null ? (float) $job['confidence_global'] : null,
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
            ],
            'suggestions' => $payloadSuggestions,
            'suggestions_summary' => $summary,
        ]);
    }

    #[Route('/enrichment-jobs/active', name: 'profile_ai_enrichment_jobs_active', methods: ['GET'])]
    public function getActiveJob(): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Utilisateur non authentifie.'], 401);
        }

        $profileId = (int) $user->getId();
        $conn = $this->em->getConnection();

        $job = $conn->fetchAssociative(
            'SELECT id, profile_id, input_type, input_value, status, attempt_count, last_error, prompt_version, confidence_global, created_at, updated_at
             FROM profile_ai_enrichment_jobs
             WHERE profile_id = :profile_id AND active_profile_id = :active_profile_id
             ORDER BY id DESC
             LIMIT 1',
            [
                'profile_id' => $profileId,
                'active_profile_id' => $profileId,
            ]
        );

        if (!$job) {
            return new JsonResponse([
                'ok' => true,
                'job' => null,
            ]);
        }

        return new JsonResponse([
            'ok' => true,
            'job' => [
                'id' => (int) $job['id'],
                'profile_id' => (int) $job['profile_id'],
                'input_type' => (string) $job['input_type'],
                'input_value' => (string) $job['input_value'],
                'status' => (string) $job['status'],
                'attempt_count' => (int) $job['attempt_count'],
                'last_error' => $job['last_error'],
                'prompt_version' => $job['prompt_version'],
                'confidence_global' => $job['confidence_global'] !== null ? (float) $job['confidence_global'] : null,
                'created_at' => $job['created_at'],
                'updated_at' => $job['updated_at'],
            ],
        ]);
    }

    #[Route('/enrichment-jobs/{id<\d+>}/decisions', name: 'profile_ai_enrichment_jobs_decisions', methods: ['POST'])]
    public function saveDecisions(int $id, Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Utilisateur non authentifie.'], 401);
        }

        $payload = $this->parseJsonPayload($request);
        if ($payload === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Payload JSON invalide.'], 400);
        }

        $decisions = $payload['decisions'] ?? null;
        if (!is_array($decisions) || empty($decisions)) {
            return new JsonResponse(['ok' => false, 'error' => 'Le tableau decisions est obligatoire.'], 400);
        }

        $profileId = (int) $user->getId();
        $conn = $this->em->getConnection();
        $now = $this->now();

        $job = $conn->fetchAssociative(
            'SELECT id, status
             FROM profile_ai_enrichment_jobs
             WHERE id = :id AND profile_id = :profile_id
             LIMIT 1',
            [
                'id' => $id,
                'profile_id' => $profileId,
            ]
        );

        if (!$job) {
            return new JsonResponse(['ok' => false, 'error' => 'Job introuvable.'], 404);
        }

        if ((string) $job['status'] !== self::JOB_STATUS_AWAITING_USER) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Ce job ne peut pas recevoir de decisions dans son statut actuel.',
                'status' => (string) $job['status'],
            ], 409);
        }

        $updated = [
            self::SUGGESTION_STATUS_ACCEPTED => 0,
            self::SUGGESTION_STATUS_EDITED => 0,
            self::SUGGESTION_STATUS_REJECTED => 0,
        ];

        $conn->beginTransaction();
        try {
            foreach ($decisions as $index => $decision) {
                if (!is_array($decision)) {
                    throw new \RuntimeException(sprintf('Decision invalide a la position %d.', $index));
                }

                $suggestionId = (int) ($decision['suggestion_id'] ?? $decision['id'] ?? 0);
                $status = trim((string) ($decision['status'] ?? ''));

                if ($suggestionId <= 0) {
                    throw new \RuntimeException(sprintf('suggestion_id manquant ou invalide a la position %d.', $index));
                }

                if (!in_array($status, [self::SUGGESTION_STATUS_ACCEPTED, self::SUGGESTION_STATUS_EDITED, self::SUGGESTION_STATUS_REJECTED], true)) {
                    throw new \RuntimeException(sprintf('status invalide pour la suggestion %d.', $suggestionId));
                }

                $exists = $conn->fetchOne(
                    'SELECT id FROM profile_ai_suggestions WHERE id = :id AND enrichment_job_id = :job_id AND profile_id = :profile_id LIMIT 1',
                    [
                        'id' => $suggestionId,
                        'job_id' => $id,
                        'profile_id' => $profileId,
                    ]
                );

                if (!$exists) {
                    throw new \RuntimeException(sprintf('La suggestion %d n\'appartient pas a ce job.', $suggestionId));
                }

                $finalValue = null;
                if ($status === self::SUGGESTION_STATUS_EDITED) {
                    if (!array_key_exists('final_value', $decision)) {
                        throw new \RuntimeException(sprintf('final_value est obligatoire pour la suggestion %d en statut edited.', $suggestionId));
                    }
                    $finalValue = $this->encodeStoredValue($decision['final_value']);
                }

                $conn->update(
                    'profile_ai_suggestions',
                    [
                        'status' => $status,
                        'final_value' => $finalValue,
                        'updated_at' => $now,
                    ],
                    [
                        'id' => $suggestionId,
                        'enrichment_job_id' => $id,
                        'profile_id' => $profileId,
                    ]
                );

                $updated[$status]++;
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();

            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }

        return new JsonResponse([
            'ok' => true,
            'job_id' => $id,
            'updated' => $updated,
        ]);
    }

    #[Route('/enrichment-jobs/{id<\d+>}/apply', name: 'profile_ai_enrichment_jobs_apply', methods: ['POST'])]
    public function applyDecisions(int $id): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user === null) {
            return new JsonResponse(['ok' => false, 'error' => 'Utilisateur non authentifie.'], 401);
        }

        $profileId = (int) $user->getId();
        $conn = $this->em->getConnection();
        $now = $this->now();

        $job = $conn->fetchAssociative(
            'SELECT id, status
             FROM profile_ai_enrichment_jobs
             WHERE id = :id AND profile_id = :profile_id
             LIMIT 1',
            [
                'id' => $id,
                'profile_id' => $profileId,
            ]
        );

        if (!$job) {
            return new JsonResponse(['ok' => false, 'error' => 'Job introuvable.'], 404);
        }

        if ((string) $job['status'] !== self::JOB_STATUS_AWAITING_USER) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'Ce job ne peut pas etre applique dans son statut actuel.',
                'status' => (string) $job['status'],
            ], 409);
        }

        $suggestions = $conn->fetchAllAssociative(
            'SELECT id, field_name, suggested_value, final_value, status
             FROM profile_ai_suggestions
             WHERE enrichment_job_id = :job_id AND profile_id = :profile_id AND status IN (:accepted, :edited)
             ORDER BY id ASC',
            [
                'job_id' => $id,
                'profile_id' => $profileId,
                'accepted' => self::SUGGESTION_STATUS_ACCEPTED,
                'edited' => self::SUGGESTION_STATUS_EDITED,
            ]
        );

        $appliedFields = [];
        $warnings = [];

        try {
            $conn->beginTransaction();

            $conn->update('profile_ai_enrichment_jobs', [
                'status' => self::JOB_STATUS_APPLYING,
                'updated_at' => $now,
                'last_error' => null,
                'active_profile_id' => $profileId,
            ], [
                'id' => $id,
                'profile_id' => $profileId,
            ]);

            foreach ($suggestions as $suggestion) {
                $fieldName = (string) $suggestion['field_name'];
                $value = $this->resolveSuggestionValueForApply($suggestion);
                [$metaKeys, $fieldWarnings] = $this->applyFieldSuggestion($profileId, $fieldName, $value);

                if (!empty($metaKeys)) {
                    $appliedFields[] = [
                        'field_name' => $fieldName,
                        'meta_keys' => $metaKeys,
                    ];
                }

                foreach ($fieldWarnings as $warning) {
                    $warnings[] = [
                        'field_name' => $fieldName,
                        'message' => $warning,
                    ];
                }
            }

            $completionRate = $this->profileCompletionCalculator->calculateForUser($user);
            $this->serviceManager->updateUserMeta($profileId, 'profile_completion_rate', (string) $completionRate);

            $conn->update('profile_ai_enrichment_jobs', [
                'status' => self::JOB_STATUS_SUCCESS,
                'updated_at' => $this->now(),
                'last_error' => null,
                'active_profile_id' => null,
            ], [
                'id' => $id,
                'profile_id' => $profileId,
            ]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();

            try {
                $conn->update('profile_ai_enrichment_jobs', [
                    'status' => self::JOB_STATUS_FAILED,
                    'updated_at' => $this->now(),
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                    'active_profile_id' => null,
                ], [
                    'id' => $id,
                    'profile_id' => $profileId,
                ]);
            } catch (\Throwable) {
                // ignore fallback update errors
            }

            return new JsonResponse([
                'ok' => false,
                'error' => 'Echec lors de l\'application des suggestions.',
                'details' => $e->getMessage(),
            ], 500);
        }

        return new JsonResponse([
            'ok' => true,
            'job_id' => $id,
            'status' => self::JOB_STATUS_SUCCESS,
            'applied_count' => count($appliedFields),
            'applied_fields' => $appliedFields,
            'warnings' => $warnings,
        ]);
    }

    private function requireAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

    private function parseJsonPayload(Request $request): ?array
    {
        $raw = trim((string) $request->getContent());
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function decodeStoredValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    private function encodeStoredValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolveSuggestionValueForApply(array $suggestion): mixed
    {
        $status = (string) ($suggestion['status'] ?? '');

        if ($status === self::SUGGESTION_STATUS_EDITED && ($suggestion['final_value'] ?? null) !== null) {
            return $this->decodeStoredValue((string) $suggestion['final_value']);
        }

        return $this->decodeStoredValue((string) ($suggestion['suggested_value'] ?? ''));
    }

    private function applyFieldSuggestion(int $profileId, string $fieldName, mixed $value): array
    {
        $metaKeys = [];
        $warnings = [];

        switch ($fieldName) {
            case 'phone':
                $phone = $this->toSingleString($value);
                if ($phone === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'billing_phone', $phone);
                $this->serviceManager->updateUserMeta($profileId, 'telephone', $phone);
                $metaKeys = ['billing_phone', 'telephone'];
                break;

            case 'main_activity':
                $activity = $this->toSingleString($value);
                if ($activity === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'activite_principale', $activity);
                $metaKeys = ['activite_principale'];
                break;

            case 'business_name':
                $businessName = $this->toSingleString($value);
                if ($businessName === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'billing_company', $businessName);
                $this->serviceManager->updateUserMeta($profileId, 'nom_commercial', $businessName);
                $metaKeys = ['billing_company', 'nom_commercial'];
                break;

            case 'siret':
                $siret = $this->normalizeSiret($this->toSingleString($value));
                if ($siret === '') {
                    $warnings[] = 'SIRET invalide ou vide, non applique.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'siret', $siret);
                $metaKeys = ['siret'];
                break;

            case 'tva_number':
            case 'tva':
                $tvaNumber = $this->normalizeTvaNumber($this->toSingleString($value));
                if ($tvaNumber === '') {
                    $warnings[] = 'Numero TVA invalide ou vide, non applique.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'tva', $tvaNumber);
                $metaKeys = ['tva'];
                break;

            case 'skills':
                $skills = $this->normalizeList($value);
                $skillValue = implode(',', $skills);
                if ($skillValue === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'competence', $skillValue);
                $metaKeys = ['competence'];
                break;

            case 'addresses':
                [$addressMetaKeys, $addressWarnings] = $this->applyAddressSuggestion($profileId, $value);
                $metaKeys = array_values(array_unique($addressMetaKeys));
                $warnings = array_merge($warnings, $addressWarnings);
                break;

            case 'experiences_text':
                $description = $this->toSingleString($value);
                if ($description === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'description', $description);
                $metaKeys = ['description'];
                break;

            case 'project_references_text':
                $reference = $this->toSingleString($value);
                if ($reference === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'reference', $reference);
                $metaKeys = ['reference'];
                break;

            case 'photos':
                $photos = $this->normalizeList($value);
                if (empty($photos)) {
                    $warnings[] = 'Aucune photo exploitable.';
                    break;
                }

                $allNumeric = true;
                foreach ($photos as $photo) {
                    if (!ctype_digit($photo)) {
                        $allNumeric = false;
                        break;
                    }
                }

                if ($allNumeric) {
                    $this->serviceManager->updateUserMeta($profileId, 'portfolio', implode(',', $photos));
                    $metaKeys = ['portfolio'];
                } else {
                    $this->serviceManager->updateUserMeta(
                        $profileId,
                        'ai_portfolio_urls',
                        json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                    $metaKeys = ['ai_portfolio_urls'];
                    $warnings[] = 'Photos stockees dans ai_portfolio_urls (URLs) en attendant import media.';
                }
                break;

            case 'videos':
                $videos = $this->normalizeList($value);
                if (empty($videos)) {
                    $warnings[] = 'Aucune video exploitable.';
                    break;
                }
                $this->serviceManager->updateUserMeta($profileId, 'video', serialize($videos));
                $metaKeys = ['video'];
                break;

            case 'avatar_url':
                $avatarUrl = $this->toSingleString($value);
                if ($avatarUrl === '') {
                    $warnings[] = 'Valeur vide, non appliquee.';
                    break;
                }
                $avatars = [];
                $avatarsMeta = $this->serviceManager->readUserMeta($profileId, 'basic_user_avatar');
                if ($avatarsMeta && $avatarsMeta->getMetaValue()) {
                    $decoded = @unserialize($avatarsMeta->getMetaValue(), ['allowed_classes' => false]);
                    if (is_array($decoded)) {
                        $avatars = $decoded;
                    }
                }
                if (!in_array($avatarUrl, $avatars, true)) {
                    $avatars[] = $avatarUrl;
                }
                $this->serviceManager->updateUserMeta($profileId, 'basic_user_avatar', serialize($avatars));
                $metaKeys = ['basic_user_avatar'];
                break;

            default:
                $warnings[] = 'Champ non mappe en MVP, aucune mise a jour appliquee.';
                break;
        }

        return [$metaKeys, $warnings];
    }

    private function normalizeSiret(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if (!is_string($digits) || strlen($digits) !== 14) {
            return '';
        }

        return $digits;
    }

    private function normalizeTvaNumber(string $value): string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value)));
        if (!is_string($compact) || $compact === '') {
            return '';
        }

        if (preg_match('/^FR[0-9A-Z]{2}[0-9]{9}$/', $compact) === 1) {
            return $compact;
        }

        if (preg_match('/^[0-9A-Z]{2}[0-9]{9}$/', $compact) === 1) {
            return 'FR' . $compact;
        }

        return '';
    }

    private function applyAddressSuggestion(int $profileId, mixed $value): array
    {
        $metaKeys = [];
        $warnings = [];

        if (is_array($value) && $this->isAssociativeArray($value)) {
            $line1 = $this->toSingleString($value['address'] ?? $value['line1'] ?? $value['billing_address_1'] ?? '');
            $city = $this->toSingleString($value['city'] ?? $value['billing_city'] ?? '');
            $postcode = $this->toSingleString($value['postal_code'] ?? $value['zip'] ?? $value['billing_postcode'] ?? '');
            $country = $this->toSingleString($value['country'] ?? $value['billing_country'] ?? '');
            $state = $this->toSingleString($value['state'] ?? $value['region'] ?? $value['billing_state'] ?? '');

            if ($line1 !== '') {
                $this->serviceManager->updateUserMeta($profileId, 'billing_address_1', $line1);
                $this->serviceManager->updateUserMeta($profileId, 'numeroNomRue_domicile', $line1);
                $metaKeys[] = 'billing_address_1';
                $metaKeys[] = 'numeroNomRue_domicile';
            }
            if ($city !== '') {
                $this->serviceManager->updateUserMeta($profileId, 'billing_city', $city);
                $this->serviceManager->updateUserMeta($profileId, 'ville_domicile', $city);
                $metaKeys[] = 'billing_city';
                $metaKeys[] = 'ville_domicile';
            }
            if ($postcode !== '') {
                $this->serviceManager->updateUserMeta($profileId, 'billing_postcode', $postcode);
                $this->serviceManager->updateUserMeta($profileId, 'codePostal_domicile', $postcode);
                $metaKeys[] = 'billing_postcode';
                $metaKeys[] = 'codePostal_domicile';
            }
            if ($country !== '') {
                $this->serviceManager->updateUserMeta($profileId, 'billing_country', $country);
                $this->serviceManager->updateUserMeta($profileId, 'pays_domicile', $country);
                $metaKeys[] = 'billing_country';
                $metaKeys[] = 'pays_domicile';
            }
            if ($state !== '') {
                $this->serviceManager->updateUserMeta($profileId, 'billing_state', $state);
                $this->serviceManager->updateUserMeta($profileId, 'region_domicile', $state);
                $metaKeys[] = 'billing_state';
                $metaKeys[] = 'region_domicile';
            }

            if (empty($metaKeys)) {
                $warnings[] = 'Objet adresse recu mais aucune valeur exploitable.';
            }

            return [$metaKeys, $warnings];
        }

        $line1 = $this->toSingleString($value);
        if ($line1 === '') {
            $warnings[] = 'Valeur vide, non appliquee.';
            return [$metaKeys, $warnings];
        }

        $this->serviceManager->updateUserMeta($profileId, 'billing_address_1', $line1);
        $this->serviceManager->updateUserMeta($profileId, 'numeroNomRue_domicile', $line1);
        $metaKeys[] = 'billing_address_1';
        $metaKeys[] = 'numeroNomRue_domicile';

        return [$metaKeys, $warnings];
    }

    private function toSingleString(mixed $value): string
    {
        if (is_array($value)) {
            $items = $this->normalizeList($value);
            return $items[0] ?? '';
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeList(mixed $value): array
    {
        $items = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) || is_object($item)) {
                    continue;
                }
                $stringItem = trim((string) $item);
                if ($stringItem !== '') {
                    $items[] = $stringItem;
                }
            }
        } else {
            $scalar = trim((string) $value);
            if ($scalar !== '') {
                if (str_contains($scalar, ',')) {
                    $parts = explode(',', $scalar);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $items[] = $part;
                        }
                    }
                } elseif (str_contains($scalar, "\n")) {
                    $parts = preg_split('/\r\n|\r|\n/', $scalar) ?: [];
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $items[] = $part;
                        }
                    }
                } else {
                    $items[] = $scalar;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
