<?php

namespace App\Entity;

use App\Entity\Traits\EntityTimestampableTrait;
use App\Repository\ReminderLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReminderLogRepository::class)]
class ReminderLog
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userDisplayName = null;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column(length: 50)]
    private string $channel = 'email';

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column(nullable: true)]
    private ?int $templateId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $payloadSummary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $contextJson = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): static
    {
        $this->userEmail = $userEmail;

        return $this;
    }

    public function getUserDisplayName(): ?string
    {
        return $this->userDisplayName;
    }

    public function setUserDisplayName(?string $userDisplayName): static
    {
        $this->userDisplayName = $userDisplayName;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

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

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function setTemplateId(?int $templateId): static
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getPayloadSummary(): ?string
    {
        return $this->payloadSummary;
    }

    public function setPayloadSummary(?string $payloadSummary): static
    {
        $this->payloadSummary = $payloadSummary;

        return $this;
    }

    public function getContextJson(): ?string
    {
        return $this->contextJson;
    }

    public function setContextJson(?string $contextJson): static
    {
        $this->contextJson = $contextJson;

        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }
}
