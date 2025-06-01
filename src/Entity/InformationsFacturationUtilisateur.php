<?php

namespace App\Entity;

use App\Repository\InformationsFacturationUtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InformationsFacturationUtilisateurRepository::class)]
class InformationsFacturationUtilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomOuSociete = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 70)]
    private ?string $ville = null;

    #[ORM\Column(length: 50)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 10)]
    private ?string $pays = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroTva = null;

    #[ORM\OneToOne(inversedBy: 'informationsFacturationUtilisateur', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $profile = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomOuSociete(): ?string
    {
        return $this->nomOuSociete;
    }

    public function setNomOuSociete(string $nomOuSociete): static
    {
        $this->nomOuSociete = $nomOuSociete;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getNumeroTva(): ?string
    {
        return $this->numeroTva;
    }

    public function setNumeroTva(?string $numeroTva): static
    {
        $this->numeroTva = $numeroTva;

        return $this;
    }

    public function getProfile(): ?User
    {
        return $this->profile;
    }

    public function setProfile(User $profile): static
    {
        $this->profile = $profile;

        return $this;
    }
}
