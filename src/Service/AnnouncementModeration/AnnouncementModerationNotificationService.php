<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;
use App\Service\AnnouncementModeration\Dto\AnnouncementModerationDecision;
use App\Service\BrevoMailer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AnnouncementModerationNotificationService
{
    public function __construct(
        private BrevoMailer $brevoMailer,
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * @return array{ok: bool, error: string}
     */
    public function sendAutoPublishNotification(AnnouncementModerationContext $context): array
    {
        $owner = $context->getOwner();
        if ($owner === null || trim((string) $owner->getEmailCanonical()) === '') {
            return ['ok' => false, 'error' => 'Owner email manquant pour la notification auto publish.'];
        }

        $templateId = $this->getUserPublishTemplateId();
        if ($templateId <= 0) {
            return ['ok' => false, 'error' => 'Template utilisateur auto publish non configure.'];
        }

        $payload = [
            'to' => [[
                'email' => (string) $owner->getEmailCanonical(),
                'name' => (string) $owner->getDisplayName(),
            ]],
            'bcc' => [[
                'email' => $this->getCommerceEmail(),
                'name' => 'Trust & Market',
            ]],
            'templateId' => $templateId,
            'params' => [
                'titre_annonce' => $context->getTitle(),
                'email' => (string) $owner->getEmailCanonical(),
                'statut_annonce' => 'Publiee',
            ],
        ];

        return $this->brevoMailer->sendTemplate($payload);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{ok: bool, error: string}
     */
    public function sendManualReviewDigest(array $items): array
    {
        if ($items === []) {
            return ['ok' => true, 'error' => ''];
        }

        $payload = [
            'to' => [[
                'email' => $this->getAdminDigestRecipient(),
                'name' => 'Trust & Market',
            ]],
            'templateId' => $this->getAdminDigestTemplateId(),
            'params' => [
                'manual_review_count' => count($items),
                'run_date' => (new \DateTimeImmutable())->format('d/m/Y H:i:s'),
                'manual_review_rows_html' => $this->buildManualReviewDigestHtml($items),
                'manual_review_rows_text' => $this->buildManualReviewDigestText($items),
            ],
        ];

        return $this->brevoMailer->sendTemplate($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildManualReviewDigestItem(
        int $jobId,
        AnnouncementModerationContext $context,
        AnnouncementModerationDecision $decision
    ): array {
        return [
            'job_id' => $jobId,
            'announcement_id' => $context->getAnnouncementId(),
            'title' => $context->getTitle(),
            'owner_email' => $context->getOwner()?->getEmailCanonical(),
            'owner_name' => $context->getOwner()?->getDisplayName(),
            'reason' => $decision->getSummary(),
            'decision_code' => $decision->getDecisionCode(),
            'front_url' => $this->buildFrontAnnouncementUrl($context),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildManualReviewDigestHtml(array $items): string
    {
        $rows = '';

        foreach ($items as $item) {
            $title = $this->escapeHtml((string) ($item['title'] ?? ''));
            $ownerEmail = $this->escapeHtml((string) ($item['owner_email'] ?? ''));
            $ownerName = $this->escapeHtml((string) ($item['owner_name'] ?? ''));
            $reason = $this->escapeHtml((string) ($item['reason'] ?? ''));
            $frontUrl = trim((string) ($item['front_url'] ?? ''));
            $linkHtml = $frontUrl !== ''
                ? sprintf('<a href="%s" target="_blank">Voir l annonce</a>', $this->escapeHtml($frontUrl))
                : '-';

            $rows .= sprintf(
                '<tr><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                (int) ($item['job_id'] ?? 0),
                (int) ($item['announcement_id'] ?? 0),
                $title,
                $ownerName,
                $ownerEmail,
                $reason,
                $linkHtml
            );
        }

        return '<html><body>'
            . '<h2>Digest moderation IA - annonces a traiter manuellement</h2>'
            . sprintf('<p>%d annonce(s) necessitent une revue manuelle.</p>', count($items))
            . '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">'
            . '<thead><tr><th>Job ID</th><th>Annonce ID</th><th>Titre</th><th>Proprietaire</th><th>Email</th><th>Raison</th><th>Lien</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '</body></html>';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildManualReviewDigestText(array $items): string
    {
        $lines = [
            sprintf('Digest moderation IA - %d annonce(s) a traiter manuellement', count($items)),
            '',
        ];

        foreach ($items as $item) {
            $lines[] = sprintf(
                'Job #%d | Annonce #%d | %s | %s | %s',
                (int) ($item['job_id'] ?? 0),
                (int) ($item['announcement_id'] ?? 0),
                (string) ($item['title'] ?? ''),
                (string) ($item['owner_email'] ?? ''),
                (string) ($item['reason'] ?? '')
            );

            $frontUrl = trim((string) ($item['front_url'] ?? ''));
            if ($frontUrl !== '') {
                $lines[] = 'Lien: ' . $frontUrl;
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function buildFrontAnnouncementUrl(AnnouncementModerationContext $context): string
    {
        $slug = $context->getSlug();
        if ($slug === '') {
            return '';
        }

        return sprintf('%s/fr/annonces/details/%s', $this->getBaseUrl(), rawurlencode($slug));
    }

    private function getUserPublishTemplateId(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_USER_PUBLISH_TEMPLATE_ID', 28);
    }

    private function getAdminDigestRecipient(): string
    {
        $value = trim($this->readStringEnv('ANNOUNCEMENT_AI_ADMIN_DIGEST_TO', $this->getCommerceEmail()));

        return $value !== '' ? $value : $this->getCommerceEmail();
    }

    private function getAdminDigestTemplateId(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_ADMIN_DIGEST_TEMPLATE_ID', 77);
    }

    private function getCommerceEmail(): string
    {
        return 'commerce@trustandmarket.com';
    }

    private function getBaseUrl(): string
    {
        $explicit = trim($this->readStringEnv('ANNOUNCEMENT_AI_BASE_URL', ''));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $environment = (string) $this->parameterBag->get('environnement');
        if ($environment === 'prod') {
            return 'https://trustandmarket.com';
        }
        if ($environment === 'rec') {
            return 'https://rec.trustandmarket.com';
        }

        return 'http://localhost';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function readIntEnv(string $name, int $default): int
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    private function readStringEnv(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }
}
