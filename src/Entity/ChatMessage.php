<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: "chat_message")]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "text")]
    private $message;

    #[ORM\Column(type: "datetime")]
    private $date_envoie;

    #[ORM\Column(type: "datetime")]
    private $date_reception;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "messages_envoyes")]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "id")]
    private $expediteur;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "messages_recus")]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "id")]
    private $destinataires;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDateEnvoie(): ?DateTimeInterface
    {
        return $this->date_envoie;
    }

    public function setDateEnvoie(DateTimeInterface $date_envoie): self
    {
        $this->date_envoie = $date_envoie;

        return $this;
    }

    public function getDateReception(): ?DateTimeInterface
    {
        return $this->date_reception;
    }

    public function setDateReception(DateTimeInterface $date_reception): self
    {
        $this->date_reception = $date_reception;

        return $this;
    }

    public function getExpediteur(): ?User
    {
        return $this->expediteur;
    }

    public function setExpediteur(?User $expediteur): self
    {
        $this->expediteur = $expediteur;

        return $this;
    }

    public function getDestinataires(): ?User
    {
        return $this->destinataires;
    }

    public function setDestinataires(?User $destinataires): self
    {
        $this->destinataires = $destinataires;

        return $this;
    }
}
