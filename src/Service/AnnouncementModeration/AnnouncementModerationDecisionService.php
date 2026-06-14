<?php

namespace App\Service\AnnouncementModeration;

use App\Service\AnnouncementModeration\Dto\AnnouncementModerationDecision;

class AnnouncementModerationDecisionService
{
    /**
     * @param array<string, mixed>|null $aiEvaluation
     */
    public function decide(bool $hardRulesPass, ?array $aiEvaluation): AnnouncementModerationDecision
    {
        if (!$hardRulesPass) {
            return new AnnouncementModerationDecision(
                'manual_review',
                'manual_review',
                'rules_only',
                'hard_rules_failed',
                'Les regles deterministes exigent une revue manuelle.',
                false,
                null,
                null,
                null
            );
        }

        if ($aiEvaluation === null) {
            return new AnnouncementModerationDecision(
                'failed',
                'failed',
                'technical_failure',
                'missing_ai_evaluation',
                'Le moteur IA n a pas renvoye de resultat exploitable.',
                true,
                null,
                null,
                null
            );
        }

        $eligible = (bool) ($aiEvaluation['eligible'] ?? false);
        $confidence = isset($aiEvaluation['confidence']) && is_numeric($aiEvaluation['confidence'])
            ? round((float) $aiEvaluation['confidence'], 4)
            : null;
        $summary = trim((string) ($aiEvaluation['summary'] ?? ''));
        $model = trim((string) ($aiEvaluation['model'] ?? ''));

        if ($eligible) {
            return new AnnouncementModerationDecision(
                'publish',
                'approved',
                'rules_and_ai',
                'auto_publish',
                $summary !== '' ? $summary : 'Annonce eligible a une publication automatique.',
                true,
                true,
                $confidence,
                $model !== '' ? $model : null
            );
        }

        return new AnnouncementModerationDecision(
            'manual_review',
            'manual_review',
            'rules_and_ai',
            'ai_manual_review',
            $summary !== '' ? $summary : 'Le moteur IA demande une revue manuelle.',
            true,
            false,
            $confidence,
            $model !== '' ? $model : null
        );
    }
}
