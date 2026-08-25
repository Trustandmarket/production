<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationContext;
use App\Service\BrevoMailer;

class AnnouncementModerationNotificationService
{
    public function __construct(
        private BrevoMailer $brevoMailer,
        private AnnouncementRejectionReasonService $rejectionReasonService
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
     * @return array{ok: bool, error: string}
     */
    public function sendAutoRejectNotification(AnnouncementModerationContext $context): array
    {
        $owner = $context->getOwner();
        if ($owner === null || trim((string) $owner->getEmailCanonical()) === '') {
            return ['ok' => false, 'error' => 'Owner email manquant pour la notification auto reject.'];
        }

        $templateId = $this->getUserRejectTemplateId();
        if ($templateId <= 0) {
            return ['ok' => false, 'error' => 'Template utilisateur auto reject non configure.'];
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
            'params' => array_merge([
                'titre_annonce' => $context->getTitle(),
                'email' => (string) $owner->getEmailCanonical(),
                'statut_annonce' => 'Rejetee',
            ], $this->buildRejectedAnnouncementTemplateParams($context->getAnnouncementId())),
        ];

        return $this->brevoMailer->sendTemplate($payload);
    }

    private function getUserPublishTemplateId(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_USER_PUBLISH_TEMPLATE_ID', 28);
    }

    private function getUserRejectTemplateId(): int
    {
        return $this->readIntEnv('ANNOUNCEMENT_AI_USER_REJECT_TEMPLATE_ID', 29);
    }

    private function getCommerceEmail(): string
    {
        return 'commerce@trustandmarket.com';
    }

    private function readIntEnv(string $name, int $default): int
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @return array<string, string|int>
     */
    private function buildRejectedAnnouncementTemplateParams(int $announcementId): array
    {
        $payload = $this->rejectionReasonService->buildForAnnouncement($announcementId);

        return [
            'nb_raisons' => (int) ($payload['nb_raisons'] ?? 0),
            'decision_summary_ai' => (string) ($payload['decision_summary_ai'] ?? ''),
            'raisons_rejet_html' => (string) ($payload['raisons_rejet_html'] ?? ''),
            'raisons_rejet_text' => (string) ($payload['raisons_rejet_text'] ?? ''),
        ];
    }
}
