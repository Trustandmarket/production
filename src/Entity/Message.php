<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use DateTimeInterface;


#[ORM\Entity(repositoryClass: "App\Repository\MessageRepository")]
#[ORM\Table(name: "wp_messages")]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\Channel", inversedBy: "messages", cascade: ["persist", "remove"])]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "id")]
    private $channel;

    #[ORM\ManyToOne(targetEntity: "App\Entity\User", inversedBy: "receivedMessages")]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "id")]
    private $addressee;

    #[ORM\ManyToOne(targetEntity: "App\Entity\User", inversedBy: "sentMessages")]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "id")]
    private $sender;

    #[ORM\Column(type: "text", nullable: false)]
    private $content;

    #[ORM\Column(type: "datetime", nullable: false)]
    private $sentDate;

    #[ORM\Column(type: "datetime", nullable: true)]
    private $receiptDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(?Channel $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getAddressee(): ?User
    {
        return $this->addressee;
    }

    public function setAddressee(?User $addressee): self
    {
        $this->addressee = $addressee;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getSentDate(): ?DateTimeInterface
    {
        return $this->sentDate;
    }

    public function setSentDate(DateTimeInterface $sentDate): self
    {
        $this->sentDate = $sentDate;

        return $this;
    }

    public function getReceipDate(): ?DateTimeInterface
    {
        return $this->receipDate;
    }

    public function setReceipDate(DateTimeInterface $receipDate): self
    {
        $this->receipDate = $receipDate;

        return $this;
    }
}
