<?php

namespace App\Entity;

use App\Entity\Traits\EntityTimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'announcement_ai_moderation_jobs')]
class AnnouncementAiModerationJob
{
    use EntityTimestampableTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_MANUAL_REVIEW = 'manual_review';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WpPosts::class)]
    #[ORM\JoinColumn(name: 'announcement_id', referencedColumnName: 'id', nullable: true)]
    private ?WpPosts $announcement = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $postStatusSnapshot = 'moderation';

    #[ORM\Column(length: 50)]
    private string $sourceTransition;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private int $attemptCount = 0;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $decisionSource = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $decisionCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $decisionSummary = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $aiModel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4, nullable: true)]
    private ?string $aiConfidence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $payloadSnapshot = null;

    #[ORM\Column(nullable: true)]
    private ?bool $hardRulesPass = null;

    #[ORM\Column(nullable: true)]
    private ?bool $aiPass = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    /**
     * @var Collection<int, AnnouncementAiModerationCheck>
     */
    #[ORM\OneToMany(mappedBy: 'moderationJob', targetEntity: AnnouncementAiModerationCheck::class)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $checks;

    public function __construct()
    {
        $this->checks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('Job #%d', $this->id ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnouncement(): ?WpPosts
    {
        return $this->announcement;
    }

    public function setAnnouncement(?WpPosts $announcement): static
    {
        $this->announcement = $announcement;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPostStatusSnapshot(): string
    {
        return $this->postStatusSnapshot;
    }

    public function setPostStatusSnapshot(string $postStatusSnapshot): static
    {
        $this->postStatusSnapshot = $postStatusSnapshot;

        return $this;
    }

    public function getSourceTransition(): string
    {
        return $this->sourceTransition;
    }

    public function setSourceTransition(string $sourceTransition): static
    {
        $this->sourceTransition = $sourceTransition;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function setAttemptCount(int $attemptCount): static
    {
        $this->attemptCount = $attemptCount;

        return $this;
    }

    public function getDecisionSource(): ?string
    {
        return $this->decisionSource;
    }

    public function setDecisionSource(?string $decisionSource): static
    {
        $this->decisionSource = $decisionSource;

        return $this;
    }

    public function getDecisionCode(): ?string
    {
        return $this->decisionCode;
    }

    public function setDecisionCode(?string $decisionCode): static
    {
        $this->decisionCode = $decisionCode;

        return $this;
    }

    public function getDecisionSummary(): ?string
    {
        return $this->decisionSummary;
    }

    public function setDecisionSummary(?string $decisionSummary): static
    {
        $this->decisionSummary = $decisionSummary;

        return $this;
    }

    public function getAiModel(): ?string
    {
        return $this->aiModel;
    }

    public function setAiModel(?string $aiModel): static
    {
        $this->aiModel = $aiModel;

        return $this;
    }

    public function getAiConfidence(): ?string
    {
        return $this->aiConfidence;
    }

    public function setAiConfidence(?string $aiConfidence): static
    {
        $this->aiConfidence = $aiConfidence;

        return $this;
    }

    public function getPayloadSnapshot(): ?string
    {
        return $this->payloadSnapshot;
    }

    public function setPayloadSnapshot(?string $payloadSnapshot): static
    {
        $this->payloadSnapshot = $payloadSnapshot;

        return $this;
    }

    public function isHardRulesPass(): ?bool
    {
        return $this->hardRulesPass;
    }

    public function setHardRulesPass(?bool $hardRulesPass): static
    {
        $this->hardRulesPass = $hardRulesPass;

        return $this;
    }

    public function isAiPass(): ?bool
    {
        return $this->aiPass;
    }

    public function setAiPass(?bool $aiPass): static
    {
        $this->aiPass = $aiPass;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    /**
     * @return Collection<int, AnnouncementAiModerationCheck>
     */
    public function getChecks(): Collection
    {
        return $this->checks;
    }

    public function addCheck(AnnouncementAiModerationCheck $check): static
    {
        if (!$this->checks->contains($check)) {
            $this->checks->add($check);
            $check->setModerationJob($this);
        }

        return $this;
    }

    public function removeCheck(AnnouncementAiModerationCheck $check): static
    {
        if ($this->checks->removeElement($check) && $check->getModerationJob() === $this) {
            $check->setModerationJob(null);
        }

        return $this;
    }

    public function getAnnouncementId(): ?int
    {
        return $this->announcement?->getId();
    }

    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function getAnnouncementLabel(): string
    {
        $title = trim((string) ($this->announcement?->getPostTitle() ?? ''));

        if ($title !== '') {
            return $title;
        }

        return sprintf('Annonce #%s', $this->getAnnouncementId() ?? '-');
    }

    public function getUserLabel(): string
    {
        $displayName = trim((string) ($this->user?->getDisplayName() ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $email = trim((string) ($this->user?->getEmailCanonical() ?? ''));
        if ($email !== '') {
            return $email;
        }

        return sprintf('Utilisateur #%s', $this->getUserId() ?? '-');
    }

    public function getDecisionLabel(): string
    {
        $parts = [];

        if ($this->decisionCode !== null && $this->decisionCode !== '') {
            $parts[] = $this->decisionCode;
        }

        if ($this->decisionSummary !== null && $this->decisionSummary !== '') {
            $parts[] = $this->decisionSummary;
        }

        if ($parts === []) {
            return '-';
        }

        return implode(' | ', $parts);
    }

    public function getAiConfidenceDisplay(): string
    {
        if ($this->aiConfidence === null || $this->aiConfidence === '') {
            return '-';
        }

        return number_format((float) $this->aiConfidence * 100, 1, ',', ' ') . ' %';
    }

    public function getChecksSummaryLabel(): string
    {
        $pass = 0;
        $fail = 0;
        $warning = 0;

        foreach ($this->checks as $check) {
            if ($check->getResult() === 'pass') {
                $pass++;
            } elseif ($check->getResult() === 'fail') {
                $fail++;
            } elseif ($check->getResult() === 'warning') {
                $warning++;
            }
        }

        return sprintf(
            'pass:%d fail:%d warning:%d',
            $pass,
            $fail,
            $warning
        );
    }

    public function getPayloadSnapshotPretty(): string
    {
        return $this->prettyPrintStoredValue($this->payloadSnapshot);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    private function prettyPrintStoredValue(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json === false ? $value : $json;
        }

        return $value;
    }
}
