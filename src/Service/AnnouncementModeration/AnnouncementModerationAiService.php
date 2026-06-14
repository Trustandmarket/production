<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationCheckResult;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;

class AnnouncementModerationAiService
{
    public function validateLiveConfiguration(): void
    {
        $apiKey = trim((string) $this->readEnv('OPENAI_API_KEY', ''));

        if ($apiKey === '') {
            throw new \RuntimeException('Variable d environnement manquante: OPENAI_API_KEY');
        }
    }

    /**
     * @return array{eligible: bool, confidence: float, summary: string, model: string, checks: array<int, AnnouncementModerationCheckResult>}
     */
    public function evaluate(AnnouncementModerationContext $context, string $mode = 'mock'): array
    {
        $mode = strtolower(trim($mode));

        if ($mode === 'live') {
            return $this->evaluateLive($context);
        }

        return $this->evaluateMock($context);
    }

    /**
     * @return array{eligible: bool, confidence: float, summary: string, model: string, checks: array<int, AnnouncementModerationCheckResult>}
     */
    private function evaluateMock(AnnouncementModerationContext $context): array
    {
        $titleLength = $this->textLength($context->getTitle());
        $descriptionLength = $this->textLength($context->getDescription());
        $titleLooksGeneric = $this->containsGenericPattern($context->getTitle());
        $descriptionLooksGeneric = $this->containsGenericPattern($context->getDescription());

        $checks = [
            $this->buildAiCheck(
                'title_quality',
                'Qualite du titre',
                $titleLength >= 12 && !$titleLooksGeneric,
                $titleLength >= 12 && !$titleLooksGeneric ? 'Titre suffisamment clair.' : 'Titre trop court ou trop generique.',
                $this->score($titleLength >= 12 && !$titleLooksGeneric, 0.92, 0.35),
                $context->getTitle()
            ),
            $this->buildAiCheck(
                'description_quality',
                'Qualite de la description',
                $descriptionLength >= 80 && !$descriptionLooksGeneric,
                $descriptionLength >= 80 && !$descriptionLooksGeneric ? 'Description suffisamment detaillee.' : 'Description trop courte ou trop generique.',
                $this->score($descriptionLength >= 80 && !$descriptionLooksGeneric, 0.9, 0.4),
                $context->getDescription()
            ),
            $this->buildAiCheck(
                'overall_coherence',
                'Coherence globale',
                $context->hasCategory() && $context->hasLocationCoreFields(),
                $context->hasCategory() && $context->hasLocationCoreFields() ? 'Annonce coherente dans son ensemble.' : 'La coherence generale n est pas suffisante.',
                $this->score($context->hasCategory() && $context->hasLocationCoreFields(), 0.88, 0.45),
                [
                    'category' => $context->hasCategory(),
                    'location' => $context->hasLocationCoreFields(),
                ]
            ),
        ];

        $eligible = true;
        $scores = [];
        foreach ($checks as $check) {
            $scores[] = (float) ($check->toArray()['score'] ?? 0.0);
            if (!$check->isPass()) {
                $eligible = false;
            }
        }

        $confidence = empty($scores) ? 0.0 : round(array_sum($scores) / count($scores), 4);

        return [
            'eligible' => $eligible,
            'confidence' => $confidence,
            'summary' => $eligible
                ? 'Mock IA: annonce jugee publiable.'
                : 'Mock IA: annonce a orienter vers une revue manuelle.',
            'model' => 'mock-announcement-moderation-v1',
            'checks' => $checks,
        ];
    }

    /**
     * @return array{eligible: bool, confidence: float, summary: string, model: string, checks: array<int, AnnouncementModerationCheckResult>}
     */
    private function evaluateLive(AnnouncementModerationContext $context): array
    {
        $config = $this->buildLiveConfig();
        $response = $this->callLlm($context, $config);

        $eligible = (bool) ($response['eligible'] ?? false);
        $confidence = is_numeric($response['confidence'] ?? null) ? (float) $response['confidence'] : 0.0;
        $summary = trim((string) ($response['summary'] ?? ''));
        $checksPayload = $response['checks'] ?? [];
        $checks = [];

        if (is_array($checksPayload)) {
            foreach ($checksPayload as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $result = strtolower(trim((string) ($item['result'] ?? '')));
                if (!in_array($result, ['pass', 'fail', 'warning'], true)) {
                    $result = $eligible ? 'pass' : 'fail';
                }

                $score = isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null;

                $checks[] = new AnnouncementModerationCheckResult(
                    trim((string) ($item['criterion_code'] ?? 'ai_check')),
                    trim((string) ($item['criterion_label'] ?? 'Check IA')),
                    'ai',
                    $result,
                    $score,
                    trim((string) ($item['reason'] ?? '')),
                    $item['raw_value'] ?? null
                );
            }
        }

        if ($checks === []) {
            $checks[] = new AnnouncementModerationCheckResult(
                'ai_global_eligibility',
                'Eligibilite globale IA',
                'ai',
                $eligible ? 'pass' : 'fail',
                $confidence,
                $summary !== '' ? $summary : 'Verdict IA global.',
                null
            );
        }

        return [
            'eligible' => $eligible,
            'confidence' => round(max(0.0, min(1.0, $confidence)), 4),
            'summary' => $summary,
            'model' => (string) $config['openai_model'],
            'checks' => $checks,
        ];
    }

    private function buildLiveConfig(): array
    {
        $openAiApiKey = trim((string) $this->readEnv('OPENAI_API_KEY', ''));
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

        return [
            'openai_api_key' => $openAiApiKey,
            'openai_base_url' => $openAiBaseUrl,
            'openai_model' => $openAiModel,
            'request_timeout' => $requestTimeout,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function callLlm(AnnouncementModerationContext $context, array $config): array
    {
        $endpoint = rtrim((string) $config['openai_base_url'], '/') . '/chat/completions';
        $body = json_encode([
            'model' => $config['openai_model'],
            'temperature' => 0.1,
            'response_format' => [
                'type' => 'json_object',
            ],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                ['role' => 'user', 'content' => $this->buildUserPrompt($context)],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new \RuntimeException('Impossible de construire le payload IA.');
        }

        $response = $this->httpRequest(
            'POST',
            $endpoint,
            [
                'Authorization: Bearer ' . $config['openai_api_key'],
                'Content-Type: application/json',
            ],
            $body,
            (int) $config['request_timeout'] + 10
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

    private function buildSystemPrompt(): string
    {
        return <<<TXT
Tu es un agent de moderation d annonces.
Tu dois repondre UNIQUEMENT en JSON valide.
Tu ne controles pas les regles techniques minimales: elles ont deja ete verifiees.
Tu dois juger la qualite editoriale et la coherence globale de l annonce.
Retour attendu:
{
  "eligible": true,
  "confidence": 0.91,
  "summary": "Phrase courte de synthese.",
  "checks": [
    {
      "criterion_code": "title_quality",
      "criterion_label": "Qualite du titre",
      "result": "pass",
      "score": 0.92,
      "reason": "Raison courte"
    }
  ]
}
TXT;
    }

    private function buildUserPrompt(AnnouncementModerationContext $context): string
    {
        $payload = [
            'announcement' => [
                'id' => $context->getAnnouncementId(),
                'title' => $context->getTitle(),
                'description' => $context->getDescription(),
                'price' => $context->getPrice(),
                'country' => $context->getCountry(),
                'city' => $context->getCity(),
                'address' => $context->getAddress(),
                'has_category' => $context->hasCategory(),
                'gallery_count' => count($context->getGalleryIds()),
            ],
            'owner' => [
                'id' => $context->getOwner()?->getId(),
                'display_name' => $context->getOwner()?->getDisplayName(),
            ],
            'evaluation_targets' => [
                'title_quality',
                'description_quality',
                'overall_coherence',
                'completeness',
            ],
            'rules' => [
                'eligible=true seulement si l annonce semble publiable sans intervention humaine',
                'eligible=false si une revue manuelle est preferable',
                'ne jamais inventer d information',
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = '{}';
        }

        return "Analyse cette annonce et retourne le JSON requis.\n\nDATA:\n" . $json;
    }

    private function containsGenericPattern(string $value): bool
    {
        $normalized = trim($value);
        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized);
        } else {
            $normalized = strtolower($normalized);
        }

        foreach (['test', 'lorem ipsum', 'annonce', 'bonjour'] as $needle) {
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $rawValue
     */
    private function buildAiCheck(
        string $code,
        string $label,
        bool $pass,
        string $reason,
        float $score,
        $rawValue
    ): AnnouncementModerationCheckResult {
        return new AnnouncementModerationCheckResult(
            $code,
            $label,
            'ai',
            $pass ? 'pass' : 'fail',
            round(max(0.0, min(1.0, $score)), 4),
            $reason,
            $rawValue
        );
    }

    private function score(bool $pass, float $passScore, float $failScore): float
    {
        return $pass ? $passScore : $failScore;
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value);
        }

        return strlen($value);
    }

    private function readEnv(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
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
                'User-Agent: TrustMarketAnnouncementAIWorker/1.0',
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
            throw new \RuntimeException('Echec requete HTTP.');
        }

        $statusCode = 0;
        $headersOut = $http_response_header ?? [];
        if (is_array($headersOut) && isset($headersOut[0]) && preg_match('/\s(\d{3})\s/', (string) $headersOut[0], $matches) === 1) {
            $statusCode = (int) $matches[1];
        }

        return [
            'status' => $statusCode,
            'body' => (string) $responseBody,
        ];
    }
}
