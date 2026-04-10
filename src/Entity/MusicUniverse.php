<?php

namespace App\Entity;

use App\Entity\Traits\EntityTimestampableTrait;
use App\Repository\MusicUniverseRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: MusicUniverseRepository::class)]
#[ORM\Table(name: "music_universes")]
#[UniqueEntity(fields: ["label"], message: "Cet univers musical existe deja.")]
#[UniqueEntity(fields: ["slug"], message: "Ce slug existe deja.")]
class MusicUniverse
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    private ?string $label = null;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    #[Gedmo\Slug(fields: ["label"])]
    private ?string $slug = null;

    #[ORM\Column(type: "boolean", options: ["default" => true])]
    private ?bool $isActive = true;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private ?int $position = 0;

    public function __toString(): string
    {
        return (string) $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}

