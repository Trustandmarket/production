<?php

namespace App\Entity;

use App\Entity\Traits\EntityTimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'announcement_ai_moderation_checks')]
class AnnouncementAiModerationCheck
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnnouncementAiModerationJob::class, inversedBy: 'checks')]
    #[ORM\JoinColumn(name: 'moderation_job_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AnnouncementAiModerationJob $moderationJob = null;

    #[ORM\Column(name: 'criterion_code', length: 80)]
    private string $criterionCode;

    #[ORM\Column(name: 'criterion_label', length: 120)]
    private string $criterionLabel;

    #[ORM\Column(name: 'source_type', length: 30)]
    private string $sourceType;

    #[ORM\Column(name: 'result', length: 20)]
    private string $result;

    #[ORM\Column(name: 'score', type: Types::DECIMAL, precision: 5, scale: 4, nullable: true)]
    private ?string $score = null;

    #[ORM\Column(name: 'reason', type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(name: 'raw_value', type: Types::TEXT, nullable: true)]
    private ?string $rawValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModerationJob(): ?AnnouncementAiModerationJob
    {
        return $this->moderationJob;
    }

    public function setModerationJob(?AnnouncementAiModerationJob $moderationJob): static
    {
        $this->moderationJob = $moderationJob;

        return $this;
    }

    public function getCriterionCode(): string
    {
        return $this->criterionCode;
    }

    public function setCriterionCode(string $criterionCode): static
    {
        $this->criterionCode = $criterionCode;

        return $this;
    }

    public function getCriterionLabel(): string
    {
        return $this->criterionLabel;
    }

    public function setCriterionLabel(string $criterionLabel): static
    {
        $this->criterionLabel = $criterionLabel;

        return $this;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getRawValue(): ?string
    {
        return $this->rawValue;
    }

    public function setRawValue(?string $rawValue): static
    {
        $this->rawValue = $rawValue;

        return $this;
    }

    public function getScoreDisplay(): string
    {
        if ($this->score === null || $this->score === '') {
            return '-';
        }

        return number_format((float) $this->score * 100, 1, ',', ' ') . ' %';
    }

    public function getRawValuePretty(): string
    {
        if ($this->rawValue === null || trim($this->rawValue) === '') {
            return '-';
        }

        $decoded = json_decode($this->rawValue, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json === false ? $this->rawValue : $json;
        }

        return $this->rawValue;
    }
}
