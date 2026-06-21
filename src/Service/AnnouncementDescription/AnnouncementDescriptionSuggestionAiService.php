<?php

namespace App\Service\AnnouncementDescription;

class AnnouncementDescriptionSuggestionAiService
{
    private const PROMPT_VERSION = 'announcement-description-v1';

    /**
     * @return array<string, mixed>
     */
    public function generateDescription(
        string $locale,
        string $categoryParentLabel,
        string $subcategoryLabel,
        string $title
    ): array {
        $config = $this->buildConfig();

        $endpoint = rtrim((string) $config['openai_base_url'], '/') . '/chat/completions';
        $payload = [
            'model' => (string) $config['openai_model'],
            'temperature' => 0.4,
            'response_format' => [
                'type' => 'json_object',
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($locale),
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildUserPrompt(
                        $locale,
                        $categoryParentLabel,
                        $subcategoryLabel,
                        $title
                    ),
                ],
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            (int) $config['request_timeout']
        );

        if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException(sprintf(
                'Echec appel OpenAI (HTTP %d).',
                (int) ($response['status'] ?? 0)
            ));
        }

        $json = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($json)) {
            throw new \RuntimeException('Reponse OpenAI non JSON.');
        }

        $content = $json['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Reponse OpenAI vide.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Le LLM n a pas renvoye un JSON exploitable.');
        }

        $description = trim((string) ($decoded['description'] ?? ''));
        if ($description === '') {
            throw new \RuntimeException('La description generee est vide.');
        }

        return [
            'description' => $description,
            'model' => (string) $config['openai_model'],
            'prompt_version' => self::PROMPT_VERSION,
            'request_payload' => $payload,
            'response_payload' => $json,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        $openAiApiKey = trim((string) $this->readEnvWithFallback(
            'ANNOUNCEMENT_DESCRIPTION_OPENAI_API_KEY',
            'OPENAI_API_KEY',
            ''
        ));
        if ($openAiApiKey === '') {
            throw new \RuntimeException('Variable d environnement manquante: OPENAI_API_KEY');
        }

        $openAiBaseUrl = trim((string) $this->readEnvWithFallback(
            'ANNOUNCEMENT_DESCRIPTION_OPENAI_BASE_URL',
            'OPENAI_BASE_URL',
            'https://api.openai.com/v1'
        ));
        if ($openAiBaseUrl === '') {
            $openAiBaseUrl = 'https://api.openai.com/v1';
        }

        $openAiModel = trim((string) $this->readEnvWithFallback(
            'ANNOUNCEMENT_DESCRIPTION_OPENAI_MODEL',
            'OPENAI_MODEL',
            'gpt-4o-mini'
        ));
        if ($openAiModel === '') {
            $openAiModel = 'gpt-4o-mini';
        }

        $requestTimeout = (int) $this->readEnvWithFallback(
            'ANNOUNCEMENT_DESCRIPTION_REQUEST_TIMEOUT',
            'AI_WORKER_REQUEST_TIMEOUT',
            '20'
        );
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

    private function buildSystemPrompt(string $locale): string
    {
        if ($locale === 'en') {
            return <<<TXT
You generate marketplace service descriptions.
You must reply ONLY with valid JSON.
Expected JSON shape:
{
  "description": "..."
}
Rules:
- Write plain text only, no Markdown, no bullet list, no HTML.
- Target about 350 words and stay between 300 and 380 words.
- Use a professional and reassuring tone.
- Use the category, subcategory, and title as the only business context.
- Do not invent certifications, years of experience, equipment, clients, locations, results, or guarantees.
- If factual details are missing, stay generic but useful and commercially clear.
- Focus on the service scope, the value for the client, the typical process, and the expected collaboration style.
TXT;
        }

        return <<<TXT
Tu rediges des descriptions de services pour une marketplace.
Tu dois repondre UNIQUEMENT en JSON valide.
Format attendu:
{
  "description": "..."
}
Regles:
- Texte brut uniquement, sans Markdown, sans liste a puces, sans HTML.
- Vise environ 350 mots et reste entre 300 et 380 mots.
- Utilise un ton professionnel, clair et rassurant.
- Appuie-toi uniquement sur la categorie, la sous-categorie et le titre comme contexte metier.
- N invente ni certifications, ni annees d experience, ni materiel, ni clients, ni lieux, ni resultats, ni garanties.
- Si des informations factuelles manquent, reste generique mais utile et commercialement clair.
- Mets l accent sur le perimetre du service, la valeur pour le client, le deroule habituel de la prestation et la maniere de collaborer.
TXT;
    }

    private function buildUserPrompt(
        string $locale,
        string $categoryParentLabel,
        string $subcategoryLabel,
        string $title
    ): string {
        $payload = [
            'locale' => $locale,
            'category_parent' => $categoryParentLabel,
            'subcategory' => $subcategoryLabel,
            'title' => $title,
            'prompt_version' => self::PROMPT_VERSION,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            $json = '{}';
        }

        if ($locale === 'en') {
            return "Generate the requested service description from this context.\n\nDATA:\n" . $json;
        }

        return "Genere la description de service demandee a partir de ce contexte.\n\nDATA:\n" . $json;
    }

    private function readEnvWithFallback(string $primaryName, string $secondaryName, string $default): string
    {
        $primary = $_SERVER[$primaryName] ?? $_ENV[$primaryName] ?? getenv($primaryName);
        if ($primary !== false && $primary !== null && trim((string) $primary) !== '') {
            return (string) $primary;
        }

        $secondary = $_SERVER[$secondaryName] ?? $_ENV[$secondaryName] ?? getenv($secondaryName);
        if ($secondary !== false && $secondary !== null) {
            return (string) $secondary;
        }

        return $default;
    }

    /**
     * @return array{status: int, body: string}
     */
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
                'User-Agent: TrustMarketAnnouncementDescriptionAI/1.0',
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
            throw new \RuntimeException('Erreur HTTP stream lors de l appel OpenAI.');
        }

        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }

        return [
            'status' => $statusCode,
            'body' => (string) $responseBody,
        ];
    }
}
