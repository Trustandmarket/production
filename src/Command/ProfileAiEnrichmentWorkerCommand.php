<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:profile-ai:worker',
    description: 'Traite les jobs d enrichissement IA en arriere-plan (mode mock pour MVP).'
)]
class ProfileAiEnrichmentWorkerCommand extends Command
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_AWAITING_USER = 'awaiting_user';
    private const STATUS_FAILED = 'failed';
    private const SUGGESTION_STATUS = 'suggested';

    private const FIELD_PHONE = 'phone';
    private const FIELD_MAIN_ACTIVITY = 'main_activity';
    private const FIELD_BUSINESS_NAME = 'business_name';
    private const FIELD_SKILLS = 'skills';
    private const FIELD_ADDRESSES = 'addresses';
    private const FIELD_EXPERIENCES_TEXT = 'experiences_text';
    private const FIELD_PROJECT_REFERENCES_TEXT = 'project_references_text';
    private const FIELD_SIRET = 'siret';
    private const FIELD_TVA_NUMBER = 'tva_number';
    private const LIVE_PROMPT_VERSION = 'live-v1';

    private const ALLOWED_FIELDS = [
        self::FIELD_PHONE,
        self::FIELD_MAIN_ACTIVITY,
        self::FIELD_BUSINESS_NAME,
        self::FIELD_SKILLS,
        self::FIELD_ADDRESSES,
        self::FIELD_EXPERIENCES_TEXT,
        self::FIELD_PROJECT_REFERENCES_TEXT,
        self::FIELD_SIRET,
        self::FIELD_TVA_NUMBER,
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode de pipeline (mock|live)', 'mock')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre max de jobs a traiter sur ce run', '10')
            ->addOption('job-id', null, InputOption::VALUE_REQUIRED, 'Traiter un job specifique (ID)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = strtolower(trim((string) $input->getOption('mode')));
        $limit = max(1, (int) $input->getOption('limit'));
        $jobIdOption = $input->getOption('job-id');
        $targetJobId = $jobIdOption !== null ? max(0, (int) $jobIdOption) : null;

        if (!in_array($mode, ['mock', 'live'], true)) {
            $io->error('Mode invalide. Valeurs supportees: mock, live.');
            return Command::INVALID;
        }

        $liveConfig = null;
        if ($mode === 'live') {
            try {
                $liveConfig = $this->buildLiveConfig();
            } catch (\Throwable $e) {
                $io->error($e->getMessage());
                return Command::INVALID;
            }
        }

        $conn = $this->em->getConnection();
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        while ($processed < $limit) {
            $candidate = $this->findNextPendingJob($conn, $targetJobId);
            if ($candidate === null) {
                break;
            }

            $jobId = (int) $candidate['id'];
            $claimed = $this->claimJob($conn, $jobId);
            if (!$claimed) {
                $skipped++;

                if ($targetJobId !== null) {
                    break;
                }

                continue;
            }

            $processed++;
            $io->text(sprintf('Traitement job #%d (profile_id=%d)...', $jobId, (int) $candidate['profile_id']));

            try {
                if ($mode === 'live') {
                    $this->processLiveJob($conn, $candidate, $liveConfig ?? []);
                } else {
                    $this->processMockJob($conn, $candidate);
                }
                $succeeded++;
                $io->text(sprintf('Job #%d -> awaiting_user', $jobId));
            } catch (\Throwable $e) {
                $failed++;
                $this->markJobAsFailed($conn, $jobId, $e->getMessage());
                $io->warning(sprintf('Job #%d en echec: %s', $jobId, $e->getMessage()));
            }

            if ($targetJobId !== null) {
                break;
            }
        }

        $io->success(sprintf(
            'Worker termine. mode=%s processed=%d success=%d failed=%d skipped=%d limit=%d%s',
            $mode,
            $processed,
            $succeeded,
            $failed,
            $skipped,
            $limit,
            $targetJobId !== null ? sprintf(' target_job_id=%d', $targetJobId) : ''
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function findNextPendingJob(Connection $conn, ?int $targetJobId): ?array
    {
        if ($targetJobId !== null && $targetJobId > 0) {
            $row = $conn->fetchAssociative(
                'SELECT id, profile_id, input_type, input_value, prompt_version, attempt_count
                 FROM profile_ai_enrichment_jobs
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
            'SELECT id, profile_id, input_type, input_value, prompt_version, attempt_count
             FROM profile_ai_enrichment_jobs
             WHERE status = :status
             ORDER BY created_at ASC, id ASC
             LIMIT 1',
            [
                'status' => self::STATUS_PENDING,
            ]
        );

        return $row ?: null;
    }

    private function claimJob(Connection $conn, int $jobId): bool
    {
        $sql = 'UPDATE profile_ai_enrichment_jobs
                SET status = :processing,
                    attempt_count = attempt_count + 1,
                    updated_at = :updated_at,
                    last_error = NULL,
                    active_profile_id = profile_id
                WHERE id = :id AND status = :pending';

        $affectedRows = $this->executeStatement($conn, $sql, [
            'processing' => self::STATUS_PROCESSING,
            'updated_at' => $this->now(),
            'id' => $jobId,
            'pending' => self::STATUS_PENDING,
        ]);

        return $affectedRows > 0;
    }

    private function processMockJob(Connection $conn, array $job): void
    {
        $jobId = (int) $job['id'];
        $profileId = (int) $job['profile_id'];
        $suggestions = $this->buildMockSuggestions($jobId, (string) $job['input_type'], (string) $job['input_value']);

        if (empty($suggestions)) {
            throw new \RuntimeException('Aucune suggestion generee en mode mock.');
        }

        $confidenceValues = array_map(static fn (array $s) => (float) $s['confidence_score'], $suggestions);
        $confidenceGlobal = array_sum($confidenceValues) / count($confidenceValues);

        $conn->beginTransaction();
        try {
            $this->executeStatement(
                $conn,
                'DELETE FROM profile_ai_suggestions WHERE enrichment_job_id = :job_id',
                ['job_id' => $jobId]
            );

            foreach ($suggestions as $suggestion) {
                $conn->insert('profile_ai_suggestions', [
                    'enrichment_job_id' => $jobId,
                    'profile_id' => $profileId,
                    'field_name' => $suggestion['field_name'],
                    'suggested_value' => $this->encodeSuggestionValue($suggestion['suggested_value']),
                    'confidence_score' => $suggestion['confidence_score'],
                    'source_type' => $suggestion['source_type'],
                    'status' => self::SUGGESTION_STATUS,
                    'final_value' => null,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);
            }

            $conn->update('profile_ai_enrichment_jobs', [
                'status' => self::STATUS_AWAITING_USER,
                'confidence_global' => round($confidenceGlobal, 4),
                'last_error' => null,
                'updated_at' => $this->now(),
                'active_profile_id' => $profileId,
            ], [
                'id' => $jobId,
            ]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function processLiveJob(Connection $conn, array $job, array $liveConfig): void
    {
        $jobId = (int) $job['id'];
        $profileId = (int) $job['profile_id'];
        $inputType = (string) $job['input_type'];
        $inputValue = trim((string) $job['input_value']);
        if ($inputValue === '') {
            throw new \RuntimeException('Le job ne contient aucune valeur d entree.');
        }

        $evidence = $this->collectLiveEvidence($inputType, $inputValue, $liveConfig);
        if (empty($evidence['sources'])) {
            throw new \RuntimeException('Aucune source exploitable recuperee (Google/scraping).');
        }

        $llmPayload = $this->generateSuggestionsFromLlm($jobId, $profileId, $inputType, $inputValue, $evidence, $liveConfig);
        $suggestions = $this->sanitizeSuggestions($llmPayload['suggestions'] ?? []);

        if (empty($suggestions)) {
            $suggestions = $this->buildFallbackSuggestionsFromEvidence($inputType, $inputValue, $evidence);
        }

        if (empty($suggestions)) {
            throw new \RuntimeException('Aucune suggestion generee apres traitement LLM.');
        }

        $globalConfidence = $this->resolveGlobalConfidence($llmPayload['global_confidence'] ?? null, $suggestions);
        $promptVersion = trim((string) ($job['prompt_version'] ?? ''));
        if ($promptVersion === '') {
            $promptVersion = self::LIVE_PROMPT_VERSION;
        }

        $conn->beginTransaction();
        try {
            $this->executeStatement(
                $conn,
                'DELETE FROM profile_ai_suggestions WHERE enrichment_job_id = :job_id',
                ['job_id' => $jobId]
            );

            foreach ($suggestions as $suggestion) {
                $conn->insert('profile_ai_suggestions', [
                    'enrichment_job_id' => $jobId,
                    'profile_id' => $profileId,
                    'field_name' => $suggestion['field_name'],
                    'suggested_value' => $this->encodeSuggestionValue($suggestion['suggested_value']),
                    'confidence_score' => $suggestion['confidence_score'],
                    'source_type' => $suggestion['source_type'],
                    'status' => self::SUGGESTION_STATUS,
                    'final_value' => null,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);
            }

            $conn->update('profile_ai_enrichment_jobs', [
                'status' => self::STATUS_AWAITING_USER,
                'confidence_global' => $globalConfidence,
                'last_error' => null,
                'prompt_version' => $promptVersion,
                'updated_at' => $this->now(),
                'active_profile_id' => $profileId,
            ], [
                'id' => $jobId,
            ]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function collectLiveEvidence(string $inputType, string $inputValue, array $liveConfig): array
    {
        $sources = [];
        $warnings = [];

        if ($inputType === 'studio_name') {
            $candidate = $this->googleFindPlaceFromText($inputValue, $liveConfig);
            if ($candidate !== null) {
                $sources[] = [
                    'source_id' => 'gp_find_1',
                    'source_type' => 'google_places_find',
                    'data' => $candidate,
                ];

                $placeId = (string) ($candidate['place_id'] ?? '');
                if ($placeId !== '') {
                    $details = $this->googleFetchPlaceDetails($placeId, $liveConfig);
                    if ($details !== null) {
                        $sources[] = [
                            'source_id' => 'gp_details_1',
                            'source_type' => 'google_places',
                            'data' => $details,
                        ];

                        $website = $this->normalizeWebsiteUrl((string) ($details['website'] ?? ''));
                        if ($website !== '') {
                            $scraping = $this->scrapeWebsite($website, $liveConfig);
                            if (!empty($scraping)) {
                                $sources[] = [
                                    'source_id' => 'ws_1',
                                    'source_type' => 'website_scraping',
                                    'data' => $scraping,
                                ];
                            }
                        }
                    }
                }
            }
        } elseif ($inputType === 'website') {
            $website = $this->normalizeWebsiteUrl($inputValue);
            if ($website === '') {
                throw new \RuntimeException('L URL website fournie est invalide.');
            }

            $scraping = $this->scrapeWebsite($website, $liveConfig);
            if (!empty($scraping)) {
                $sources[] = [
                    'source_id' => 'ws_1',
                    'source_type' => 'website_scraping',
                    'data' => $scraping,
                ];
            }

            $textQuery = $this->buildPlaceQueryFromWebsite($website, $scraping);
            if ($textQuery !== '') {
                $candidate = $this->googleFindPlaceFromText($textQuery, $liveConfig);
                if ($candidate !== null) {
                    $sources[] = [
                        'source_id' => 'gp_find_1',
                        'source_type' => 'google_places_find',
                        'data' => $candidate,
                    ];

                    $placeId = (string) ($candidate['place_id'] ?? '');
                    if ($placeId !== '') {
                        $details = $this->googleFetchPlaceDetails($placeId, $liveConfig);
                        if ($details !== null) {
                            $sources[] = [
                                'source_id' => 'gp_details_1',
                                'source_type' => 'google_places',
                                'data' => $details,
                            ];
                        }
                    }
                }
            }
        } else {
            throw new \RuntimeException(sprintf('input_type non supporte pour le mode live: %s', $inputType));
        }

        if (empty($sources)) {
            $warnings[] = 'Aucune source n a pu etre recuperee.';
        }

        return [
            'sources' => $sources,
            'warnings' => $warnings,
        ];
    }

    private function googleFindPlaceFromText(string $query, array $liveConfig): ?array
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json';
        $url = $endpoint . '?' . http_build_query([
            'input' => $query,
            'inputtype' => 'textquery',
            'fields' => 'place_id,name,formatted_address,business_status,geometry,rating',
            'language' => 'fr',
            'key' => $liveConfig['google_places_api_key'],
        ]);

        $response = $this->httpRequest('GET', $url, [], null, (int) $liveConfig['request_timeout']);
        if (($response['status'] ?? 0) !== 200) {
            return null;
        }

        $json = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($json)) {
            return null;
        }

        if (($json['status'] ?? '') !== 'OK') {
            return null;
        }

        $candidates = $json['candidates'] ?? null;
        if (!is_array($candidates) || empty($candidates) || !is_array($candidates[0])) {
            return null;
        }

        return $candidates[0];
    }

    private function googleFetchPlaceDetails(string $placeId, array $liveConfig): ?array
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
        $url = $endpoint . '?' . http_build_query([
            'place_id' => $placeId,
            'fields' => 'place_id,name,formatted_address,formatted_phone_number,international_phone_number,website,types,url,business_status,rating,user_ratings_total',
            'language' => 'fr',
            'key' => $liveConfig['google_places_api_key'],
        ]);

        $response = $this->httpRequest('GET', $url, [], null, (int) $liveConfig['request_timeout']);
        if (($response['status'] ?? 0) !== 200) {
            return null;
        }

        $json = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($json)) {
            return null;
        }

        if (($json['status'] ?? '') !== 'OK') {
            return null;
        }

        $result = $json['result'] ?? null;
        if (!is_array($result)) {
            return null;
        }

        return $result;
    }

    private function scrapeWebsite(string $url, array $liveConfig): array
    {
        $response = $this->httpRequest(
            'GET',
            $url,
            [
                'User-Agent: ' . $liveConfig['http_user_agent'],
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            null,
            (int) $liveConfig['request_timeout']
        );

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 400) {
            return [];
        }

        $html = (string) ($response['body'] ?? '');
        if ($html === '') {
            return [];
        }

        $html = substr($html, 0, 500000);
        $title = $this->extractTitle($html);
        $og = $this->extractOpenGraph($html);
        $phones = $this->extractPhoneNumbers($html);
        $socialLinks = $this->extractSocialLinks($html);
        $addressHint = $this->extractAddressHint($html);
        $siret = $this->extractSiretFromHtml($html);
        $tvaNumber = $this->extractTvaNumberFromHtml($html);

        return [
            'url' => $url,
            'title' => $title,
            'og_title' => $og['og:title'] ?? null,
            'og_description' => $og['og:description'] ?? null,
            'og_url' => $og['og:url'] ?? null,
            'phones_found' => $phones,
            'social_links' => $socialLinks,
            'address_hint' => $addressHint,
            'siret' => $siret,
            'tva_number' => $tvaNumber,
        ];
    }

    private function generateSuggestionsFromLlm(
        int $jobId,
        int $profileId,
        string $inputType,
        string $inputValue,
        array $evidence,
        array $liveConfig
    ): array {
        $endpoint = rtrim((string) $liveConfig['openai_base_url'], '/') . '/chat/completions';
        $systemPrompt = $this->buildLiveSystemPrompt();
        $userPrompt = $this->buildLiveUserPrompt($jobId, $profileId, $inputType, $inputValue, $evidence);

        $body = json_encode([
            'model' => $liveConfig['openai_model'],
            'temperature' => 0.2,
            'response_format' => [
                'type' => 'json_object',
            ],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new \RuntimeException('Impossible de construire le payload LLM.');
        }

        $response = $this->httpRequest(
            'POST',
            $endpoint,
            [
                'Authorization: Bearer ' . $liveConfig['openai_api_key'],
                'Content-Type: application/json',
            ],
            $body,
            (int) $liveConfig['request_timeout'] + 10
        );

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException(sprintf('Echec appel LLM (HTTP %d).', (int) ($response['status'] ?? 0)));
        }

        $json = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($json)) {
            throw new \RuntimeException('Reponse LLM non JSON.');
        }

        $content = $json['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Reponse LLM vide.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Le LLM n a pas renvoye un JSON exploitable.');
        }

        return $decoded;
    }

    private function sanitizeSuggestions(array $suggestions): array
    {
        $result = [];
        foreach ($suggestions as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }

            $fieldName = trim((string) ($suggestion['field_name'] ?? ''));
            if (!in_array($fieldName, self::ALLOWED_FIELDS, true)) {
                continue;
            }

            $suggestedValue = $suggestion['suggested_value'] ?? null;
            if ($suggestedValue === null) {
                continue;
            }

            $sourceType = trim((string) ($suggestion['source_type'] ?? 'merged'));
            if ($sourceType === '') {
                $sourceType = 'merged';
            }

            $confidence = (float) ($suggestion['confidence_score'] ?? 0.0);
            if ($confidence < 0) {
                $confidence = 0.0;
            }
            if ($confidence > 1) {
                $confidence = 1.0;
            }

            $normalizedValue = $this->normalizeSuggestionValueByField($fieldName, $suggestedValue);
            if ($normalizedValue === null) {
                continue;
            }

            $result[] = [
                'field_name' => $fieldName,
                'suggested_value' => $normalizedValue,
                'confidence_score' => round($confidence, 4),
                'source_type' => $sourceType,
            ];
        }

        $dedup = [];
        foreach ($result as $item) {
            $dedup[$item['field_name']] = $item;
        }

        return array_values($dedup);
    }

    private function normalizeSuggestionValueByField(string $fieldName, mixed $value): mixed
    {
        switch ($fieldName) {
            case self::FIELD_PHONE:
            case self::FIELD_MAIN_ACTIVITY:
            case self::FIELD_BUSINESS_NAME:
            case self::FIELD_EXPERIENCES_TEXT:
            case self::FIELD_PROJECT_REFERENCES_TEXT:
                $text = trim((string) $value);
                return $text !== '' ? $text : null;

            case self::FIELD_SIRET:
                return $this->normalizeSiretValue($value);

            case self::FIELD_TVA_NUMBER:
                return $this->normalizeTvaNumberValue($value);

            case self::FIELD_SKILLS:
                $items = $this->normalizeStringList($value);
                return !empty($items) ? $items : null;

            case self::FIELD_ADDRESSES:
                if (is_array($value)) {
                    if ($this->isAssociative($value)) {
                        return $value;
                    }

                    if (!empty($value)) {
                        $first = $value[0];
                        if (is_array($first) && $this->isAssociative($first)) {
                            return $first;
                        }

                        $firstString = trim((string) $first);
                        return $firstString !== '' ? $firstString : null;
                    }

                    return null;
                }

                $text = trim((string) $value);
                return $text !== '' ? $text : null;
        }

        return null;
    }

    private function buildFallbackSuggestionsFromEvidence(string $inputType, string $inputValue, array $evidence): array
    {
        $merged = $this->flattenEvidence($evidence);
        $suggestions = [];

        $businessName = trim((string) ($merged['name'] ?? $merged['og_title'] ?? $inputValue));
        if ($businessName !== '') {
            $suggestions[] = [
                'field_name' => self::FIELD_BUSINESS_NAME,
                'suggested_value' => $businessName,
                'confidence_score' => 0.7,
                'source_type' => 'merged',
            ];
        }

        $phone = trim((string) ($merged['international_phone_number'] ?? $merged['formatted_phone_number'] ?? ($merged['phones_found'][0] ?? '')));
        if ($phone !== '') {
            $suggestions[] = [
                'field_name' => self::FIELD_PHONE,
                'suggested_value' => $phone,
                'confidence_score' => 0.72,
                'source_type' => 'merged',
            ];
        }

        $siret = $this->extractSiretCandidate($merged);
        if ($siret !== null) {
            $suggestions[] = [
                'field_name' => self::FIELD_SIRET,
                'suggested_value' => $siret,
                'confidence_score' => 0.76,
                'source_type' => 'merged',
            ];
        }

        $tvaNumber = $this->extractTvaNumberCandidate($merged);
        if ($tvaNumber !== null) {
            $suggestions[] = [
                'field_name' => self::FIELD_TVA_NUMBER,
                'suggested_value' => $tvaNumber,
                'confidence_score' => 0.74,
                'source_type' => 'merged',
            ];
        }

        $address = trim((string) ($merged['formatted_address'] ?? $merged['address_hint'] ?? ''));
        if ($address !== '') {
            $suggestions[] = [
                'field_name' => self::FIELD_ADDRESSES,
                'suggested_value' => $address,
                'confidence_score' => 0.68,
                'source_type' => 'merged',
            ];
        }

        return $suggestions;
    }

    private function flattenEvidence(array $evidence): array
    {
        $result = [];
        $sources = $evidence['sources'] ?? [];
        if (!is_array($sources)) {
            return $result;
        }

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $data = $source['data'] ?? null;
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $key => $value) {
                if (!array_key_exists($key, $result) || $result[$key] === null || $result[$key] === '') {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    private function buildLiveConfig(): array
    {
        $googlePlacesApiKey = trim((string) $this->readEnv('GOOGLE_PLACES_API_KEY', ''));
        $openAiApiKey = trim((string) $this->readEnv('OPENAI_API_KEY', ''));
        if ($googlePlacesApiKey === '') {
            throw new \RuntimeException('Variable d environnement manquante: GOOGLE_PLACES_API_KEY');
        }
        if ($openAiApiKey === '') {
            throw new \RuntimeException('Variable d environnement manquante: OPENAI_API_KEY');
        }

        $openAiBaseUrl = trim((string) $this->readEnv('OPENAI_BASE_URL', 'https://api.openai.com/v1'));
        if ($openAiBaseUrl === '') {
            $openAiBaseUrl = 'https://api.openai.com/v1';
        }
        $openAiModel = trim((string) $this->readEnv('OPENAI_MODEL', 'gpt-4o-mini'));
        if ($openAiModel === '') {
            $openAiModel = 'gpt-4o-mini';
        }

        $requestTimeout = (int) $this->readEnv('AI_WORKER_REQUEST_TIMEOUT', '20');
        if ($requestTimeout < 5) {
            $requestTimeout = 5;
        }
        if ($requestTimeout > 90) {
            $requestTimeout = 90;
        }

        $userAgent = trim((string) $this->readEnv('AI_WORKER_USER_AGENT', 'TrustMarketProfileAIWorker/1.0'));
        if ($userAgent === '') {
            $userAgent = 'TrustMarketProfileAIWorker/1.0';
        }

        return [
            'google_places_api_key' => $googlePlacesApiKey,
            'openai_api_key' => $openAiApiKey,
            'openai_base_url' => $openAiBaseUrl,
            'openai_model' => $openAiModel,
            'request_timeout' => $requestTimeout,
            'http_user_agent' => $userAgent,
        ];
    }

    private function buildLiveSystemPrompt(): string
    {
        return <<<TXT
Tu es un agent d enrichissement de profil professionnel.
Tu dois repondre UNIQUEMENT en JSON valide.
Regles:
- N invente aucune information qui n est pas presente dans les preuves.
- Propose seulement des champs parmi: phone, main_activity, business_name, skills, addresses, experiences_text, project_references_text, siret, tva_number.
- Pour chaque suggestion, fournis: field_name, suggested_value, confidence_score (0..1), source_type.
- Si une valeur est incertaine, baisse confidence_score.
- Utilise un tableau JSON pour skills.
- Tu peux renvoyer une adresse soit en string, soit en objet {address, city, postal_code, country, state}.
- Format final attendu:
{
  "global_confidence": 0.0,
  "suggestions": [
    {
      "field_name": "...",
      "suggested_value": "...",
      "confidence_score": 0.0,
      "source_type": "google_places|website_scraping|merged|llm"
    }
  ]
}
TXT;
    }

    private function buildLiveUserPrompt(
        int $jobId,
        int $profileId,
        string $inputType,
        string $inputValue,
        array $evidence
    ): string {
        $payload = [
            'job_id' => $jobId,
            'profile_id' => $profileId,
            'input_type' => $inputType,
            'input_value' => $inputValue,
            'allowed_fields' => self::ALLOWED_FIELDS,
            'evidence' => $evidence['sources'] ?? [],
            'warnings' => $evidence['warnings'] ?? [],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = '{}';
        }

        return "Analyse ces preuves et retourne les suggestions au format JSON requis.\n\nDATA:\n" . $json;
    }

    private function resolveGlobalConfidence(mixed $candidateValue, array $suggestions): float
    {
        if (is_numeric($candidateValue)) {
            $value = (float) $candidateValue;
            if ($value < 0) {
                return 0.0;
            }
            if ($value > 1) {
                return 1.0;
            }
            return round($value, 4);
        }

        if (empty($suggestions)) {
            return 0.0;
        }

        $scores = [];
        foreach ($suggestions as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }
            $scores[] = (float) ($suggestion['confidence_score'] ?? 0.0);
        }
        if (empty($scores)) {
            return 0.0;
        }

        $avg = array_sum($scores) / count($scores);
        if ($avg < 0) {
            $avg = 0.0;
        }
        if ($avg > 1) {
            $avg = 1.0;
        }

        return round($avg, 4);
    }

    private function normalizeWebsiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '';
        if (!is_string($path)) {
            $path = '';
        }

        $normalized = $scheme . '://' . $host;
        if ($path !== '') {
            $normalized .= $path;
        }

        return rtrim($normalized, '/');
    }

    private function buildPlaceQueryFromWebsite(string $websiteUrl, array $scraping): string
    {
        $ogTitle = trim((string) ($scraping['og_title'] ?? ''));
        if ($ogTitle !== '') {
            return $ogTitle;
        }

        $title = trim((string) ($scraping['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $host = parse_url($websiteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $host = preg_replace('/^www\./i', '', $host);
        $host = preg_replace('/\.[a-z0-9]+$/i', '', $host);
        $host = str_replace(['-', '_', '.'], ' ', $host);
        return trim((string) $host);
    }

    private function normalizeStringList(mixed $value): array
    {
        $items = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item) || is_object($item)) {
                    continue;
                }
                $item = trim((string) $item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        } else {
            $text = trim((string) $value);
            if ($text !== '') {
                if (str_contains($text, ',')) {
                    $parts = explode(',', $text);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part !== '') {
                            $items[] = $part;
                        }
                    }
                } else {
                    $items[] = $text;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function normalizeSiretValue(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value));
        if (!is_string($digits) || strlen($digits) !== 14) {
            return null;
        }

        return $digits;
    }

    private function normalizeTvaNumberValue(mixed $value): ?string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value)));
        if (!is_string($compact) || $compact === '') {
            return null;
        }

        if (preg_match('/^FR[0-9A-Z]{2}[0-9]{9}$/', $compact) === 1) {
            return $compact;
        }

        if (preg_match('/^[0-9A-Z]{2}[0-9]{9}$/', $compact) === 1) {
            return 'FR' . $compact;
        }

        return null;
    }

    private function extractSiretCandidate(array $merged): ?string
    {
        foreach (['siret', 'siret_number'] as $key) {
            if (!array_key_exists($key, $merged)) {
                continue;
            }

            $normalized = $this->normalizeSiretValue($merged[$key]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $candidate = $this->findFirstRegexMatchInValue($merged, '/(?:\d[\s\.\-]?){14}/');
        if ($candidate === null) {
            return null;
        }

        return $this->normalizeSiretValue($candidate);
    }

    private function extractTvaNumberCandidate(array $merged): ?string
    {
        foreach (['tva_number', 'vat_number', 'tva', 'vat'] as $key) {
            if (!array_key_exists($key, $merged)) {
                continue;
            }

            $normalized = $this->normalizeTvaNumberValue($merged[$key]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $candidate = $this->findFirstRegexMatchInValue($merged, '/FR[\s\.\-]*[0-9A-Z]{2}[\s\.\-]*(?:\d[\s\.\-]*){9}/i');
        if ($candidate === null) {
            return null;
        }

        return $this->normalizeTvaNumberValue($candidate);
    }

    private function findFirstRegexMatchInValue(mixed $value, string $pattern): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $candidate = $this->findFirstRegexMatchInValue($item, $pattern);
                if ($candidate !== null) {
                    return $candidate;
                }
            }

            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return (string) ($matches[0] ?? '');
    }

    private function isAssociative(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(strip_tags((string) $matches[1]));
            return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    private function extractOpenGraph(string $html): array
    {
        $result = [];
        $regex = '/<meta\s+[^>]*(?:property|name)\s*=\s*["\'](og:[^"\']+)["\'][^>]*content\s*=\s*["\']([^"\']*)["\'][^>]*>/i';
        if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower(trim((string) ($match[1] ?? '')));
                $value = trim(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($key !== '' && $value !== '') {
                    $result[$key] = $value;
                }
            }
        }

        $regexReverse = '/<meta\s+[^>]*content\s*=\s*["\']([^"\']*)["\'][^>]*(?:property|name)\s*=\s*["\'](og:[^"\']+)["\'][^>]*>/i';
        if (preg_match_all($regexReverse, $html, $matchesReverse, PREG_SET_ORDER)) {
            foreach ($matchesReverse as $match) {
                $key = strtolower(trim((string) ($match[2] ?? '')));
                $value = trim(html_entity_decode((string) ($match[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($key !== '' && $value !== '' && !array_key_exists($key, $result)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    private function extractPhoneNumbers(string $html): array
    {
        $phones = [];
        $clean = preg_replace('/\s+/', ' ', $html);
        if (!is_string($clean)) {
            return [];
        }

        if (preg_match_all('/\+?\d[\d\.\s\-\(\)]{7,}\d/', $clean, $matches)) {
            foreach ($matches[0] as $rawPhone) {
                $rawPhone = trim((string) $rawPhone);
                $normalized = preg_replace('/[^\d\+]/', '', $rawPhone);
                if (!is_string($normalized)) {
                    continue;
                }
                $normalized = trim($normalized);
                if ($normalized === '') {
                    continue;
                }
                if (strlen(preg_replace('/\D/', '', $normalized)) < 8) {
                    continue;
                }
                $phones[] = $normalized;
            }
        }

        return array_values(array_unique($phones));
    }

    private function extractSocialLinks(string $html): array
    {
        $links = [];
        if (preg_match_all('/https?:\/\/[^\s"\']+/i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }
                $lower = strtolower($url);
                if (
                    str_contains($lower, 'instagram.com') ||
                    str_contains($lower, 'facebook.com') ||
                    str_contains($lower, 'youtube.com') ||
                    str_contains($lower, 'youtu.be') ||
                    str_contains($lower, 'linkedin.com') ||
                    str_contains($lower, 'tiktok.com')
                ) {
                    $links[] = $url;
                }
            }
        }

        return array_values(array_unique($links));
    }

    private function extractAddressHint(string $html): string
    {
        $text = strip_tags($html);
        if (!is_string($text)) {
            return '';
        }
        $text = preg_replace('/\s+/', ' ', $text);
        if (!is_string($text) || $text === '') {
            return '';
        }

        if (preg_match('/\b\d{5}\b[^\.]{0,80}/u', $text, $match)) {
            return trim((string) $match[0]);
        }

        return '';
    }

    private function extractSiretFromHtml(string $html): ?string
    {
        $text = strip_tags($html);
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        if (preg_match('/(?:\d[\s\.\-]?){14}/', $text, $matches) !== 1) {
            return null;
        }

        return $this->normalizeSiretValue((string) ($matches[0] ?? ''));
    }

    private function extractTvaNumberFromHtml(string $html): ?string
    {
        $text = strip_tags($html);
        if (!is_string($text) || trim($text) === '') {
            return null;
        }

        if (preg_match('/FR[\s\.\-]*[0-9A-Z]{2}[\s\.\-]*(?:\d[\s\.\-]*){9}/i', $text, $matches) !== 1) {
            return null;
        }

        return $this->normalizeTvaNumberValue((string) ($matches[0] ?? ''));
    }

    private function httpRequest(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            $method = 'GET';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('Impossible d initialiser cURL.');
            }

            $finalHeaders = array_merge([
                'User-Agent: TrustMarketProfileAIWorker/1.0',
            ], $headers);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

            if ($body !== null && $method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $responseBody = curl_exec($ch);
            if ($responseBody === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException('Erreur HTTP cURL: ' . $error);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'status' => $statusCode,
                'body' => (string) $responseBody,
            ];
        }

        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ];
        if ($body !== null && $method !== 'GET') {
            $contextOptions['http']['content'] = $body;
        }

        $context = stream_context_create($contextOptions);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new \RuntimeException('Erreur HTTP via stream context.');
        }

        $statusCode = 0;
        if (function_exists('http_get_last_response_headers')) {
            $headersList = http_get_last_response_headers();
            if (is_array($headersList) && !empty($headersList[0])) {
                if (preg_match('/\s(\d{3})\s/', (string) $headersList[0], $m)) {
                    $statusCode = (int) $m[1];
                }
            }
        } elseif (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
                $statusCode = (int) $m[1];
            }
        }

        return [
            'status' => $statusCode,
            'body' => (string) $responseBody,
        ];
    }

    private function readEnv(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== null) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return $default;
    }

    private function markJobAsFailed(Connection $conn, int $jobId, string $error): void
    {
        $safeError = substr(trim($error), 0, 1000);
        if ($safeError === '') {
            $safeError = 'Erreur inconnue.';
        }

        $conn->update('profile_ai_enrichment_jobs', [
            'status' => self::STATUS_FAILED,
            'last_error' => $safeError,
            'updated_at' => $this->now(),
            'active_profile_id' => null,
        ], [
            'id' => $jobId,
        ]);
    }

    private function buildMockSuggestions(int $jobId, string $inputType, string $inputValue): array
    {
        $seed = (int) sprintf('%u', crc32($jobId . '|' . $inputType . '|' . $inputValue));

        $businessName = $this->buildBusinessName($inputType, $inputValue);
        $mainActivity = $this->pickFromList($seed, [
            'Production musicale',
            'Studio d enregistrement',
            'Photographie evenementielle',
            'Videographie corporate',
            'Mixage et mastering',
        ]);

        $skills = $this->pickManyFromList($seed, [
            'Enregistrement',
            'Mixage',
            'Mastering',
            'Captation video',
            'Photographie',
            'Direction artistique',
            'Montage audio',
            'Montage video',
        ], 4);

        $city = $this->pickFromList($seed + 11, ['Paris', 'Lyon', 'Marseille', 'Lille', 'Bordeaux']);
        $postcode = $this->pickFromList($seed + 17, ['75011', '69003', '13006', '59800', '33000']);
        $streetNumber = (string) (($seed % 80) + 1);
        $streetName = $this->pickFromList($seed + 23, ['Rue Oberkampf', 'Rue de Rivoli', 'Rue Paradis', 'Avenue Jean Jaures', 'Rue Nationale']);

        $addressObject = [
            'address' => sprintf('%s %s', $streetNumber, $streetName),
            'city' => $city,
            'postal_code' => $postcode,
            'country' => 'FR',
            'state' => $city,
        ];

        $phone = '+33' . str_pad((string) (($seed % 900000000) + 100000000), 9, '0', STR_PAD_LEFT);
        $siren = str_pad((string) (($seed % 900000000) + 100000000), 9, '0', STR_PAD_LEFT);
        $nic = str_pad((string) (($seed % 99999) + 1), 5, '0', STR_PAD_LEFT);
        $siret = $siren . $nic;
        $tvaKey = str_pad(strtoupper(base_convert((string) ($seed % 1296), 10, 36)), 2, '0', STR_PAD_LEFT);
        $tvaNumber = 'FR' . $tvaKey . $siren;

        return [
            [
                'field_name' => self::FIELD_PHONE,
                'suggested_value' => $phone,
                'confidence_score' => 0.93,
                'source_type' => $inputType === 'studio_name' ? 'google_places' : 'website_scraping',
            ],
            [
                'field_name' => self::FIELD_MAIN_ACTIVITY,
                'suggested_value' => $mainActivity,
                'confidence_score' => 0.84,
                'source_type' => 'merged',
            ],
            [
                'field_name' => self::FIELD_BUSINESS_NAME,
                'suggested_value' => $businessName,
                'confidence_score' => 0.88,
                'source_type' => $inputType === 'studio_name' ? 'google_places' : 'website_scraping',
            ],
            [
                'field_name' => self::FIELD_SKILLS,
                'suggested_value' => $skills,
                'confidence_score' => 0.78,
                'source_type' => 'llm',
            ],
            [
                'field_name' => self::FIELD_ADDRESSES,
                'suggested_value' => $addressObject,
                'confidence_score' => 0.91,
                'source_type' => 'google_places',
            ],
            [
                'field_name' => self::FIELD_EXPERIENCES_TEXT,
                'suggested_value' => sprintf(
                    '%s accompagne des projets depuis plusieurs annees, avec un accompagnement de la conception a la livraison finale.',
                    $businessName
                ),
                'confidence_score' => 0.73,
                'source_type' => 'llm',
            ],
            [
                'field_name' => self::FIELD_PROJECT_REFERENCES_TEXT,
                'suggested_value' => sprintf(
                    'References: clips independants, captations live, campagnes social media, podcasts et productions corporate (%s).',
                    $city
                ),
                'confidence_score' => 0.71,
                'source_type' => 'llm',
            ],
            [
                'field_name' => self::FIELD_SIRET,
                'suggested_value' => $siret,
                'confidence_score' => 0.9,
                'source_type' => 'merged',
            ],
            [
                'field_name' => self::FIELD_TVA_NUMBER,
                'suggested_value' => $tvaNumber,
                'confidence_score' => 0.88,
                'source_type' => 'merged',
            ],
        ];
    }

    private function buildBusinessName(string $inputType, string $inputValue): string
    {
        $inputValue = trim($inputValue);
        if ($inputValue === '') {
            return 'Studio Demo';
        }

        if ($inputType === 'website') {
            $host = parse_url($inputValue, PHP_URL_HOST);
            if (!$host) {
                $host = $inputValue;
            }
            $host = preg_replace('/^www\./i', '', (string) $host);
            $host = preg_replace('/\.[a-z0-9]+$/i', '', (string) $host);
            $host = str_replace(['-', '_', '.'], ' ', (string) $host);
            $host = trim($host);
            if ($host !== '') {
                return ucwords($host);
            }
        }

        return $inputValue;
    }

    private function pickFromList(int $seed, array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $index = $seed % count($items);
        return (string) $items[$index];
    }

    private function pickManyFromList(int $seed, array $items, int $count): array
    {
        if (empty($items) || $count <= 0) {
            return [];
        }

        $pool = array_values($items);
        $result = [];
        $localSeed = $seed;

        while (!empty($pool) && count($result) < $count) {
            $index = $localSeed % count($pool);
            $result[] = (string) $pool[$index];
            array_splice($pool, $index, 1);
            $localSeed = (int) (($localSeed * 1103515245 + 12345) & 0x7fffffff);
        }

        return array_values(array_unique($result));
    }

    private function encodeSuggestionValue(mixed $value): string
    {
        if ($value === null) {
            return '';
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

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '';
        }

        return $json;
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

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
