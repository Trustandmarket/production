<?php

namespace App\Entity;

use App\Repository\ChannelRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;


#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\Table(name: "wp_channels")]
class Channel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\OneToMany(targetEntity: "App\Entity\Message", mappedBy: "channel")]
    private $messages;

    #[ORM\Column(type: "string", length: 190, unique: true)]
    private $name;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "channels1")]
    #[ORM\JoinColumn(nullable: true, referencedColumnName: "id")]
    protected $firstUser;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "channels2")]
    #[ORM\JoinColumn(nullable: true, referencedColumnName: "id")]
    protected $secondUser;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFirstUser(): ?User
    {
        return $this->firstUser;
    }

    public function setFirstUser(?User $firstUser): self
    {
        $this->firstUser = $firstUser;

        return $this;
    }

    public function getSecondUser(): ?User
    {
        return $this->secondUser;
    }

    public function setSecondUser(?User $secondUser): self
    {
        $this->secondUser = $secondUser;

        return $this;
    }

    /**
     * @return Collection|Message[]
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }
}
