<?php

namespace App\Service\AnnouncementModeration\Dto;

class AnnouncementModerationCheckResult
{
    /**
     * @param mixed $rawValue
     */
    public function __construct(
        private string $criterionCode,
        private string $criterionLabel,
        private string $sourceType,
        private string $result,
        private ?float $score,
        private string $reason,
        private $rawValue
    ) {
    }

    public function isPass(): bool
    {
        return $this->result === 'pass';
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getCriterionCode(): string
    {
        return $this->criterionCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'criterion_code' => $this->criterionCode,
            'criterion_label' => $this->criterionLabel,
            'source_type' => $this->sourceType,
            'result' => $this->result,
            'score' => $this->score,
            'reason' => $this->reason,
            'raw_value' => $this->rawValue,
        ];
    }
}
