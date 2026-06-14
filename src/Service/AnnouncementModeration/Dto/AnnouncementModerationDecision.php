<?php

namespace App\Service\AnnouncementModeration\Dto;

class AnnouncementModerationDecision
{
    public function __construct(
        private string $outcome,
        private string $jobStatus,
        private string $decisionSource,
        private string $decisionCode,
        private string $summary,
        private bool $hardRulesPass,
        private ?bool $aiPass,
        private ?float $aiConfidence,
        private ?string $aiModel
    ) {
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getJobStatus(): string
    {
        return $this->jobStatus;
    }

    public function getDecisionSource(): string
    {
        return $this->decisionSource;
    }

    public function getDecisionCode(): string
    {
        return $this->decisionCode;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function isHardRulesPass(): bool
    {
        return $this->hardRulesPass;
    }

    public function isAiPass(): ?bool
    {
        return $this->aiPass;
    }

    public function getAiConfidence(): ?float
    {
        return $this->aiConfidence;
    }

    public function getAiModel(): ?string
    {
        return $this->aiModel;
    }
}
