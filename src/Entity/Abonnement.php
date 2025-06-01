<?php

namespace App\Entity;

use App\Repository\AbonnementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\EntityTimestampableTrait;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
class Abonnement
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'abonnements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'abonnements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OffreInterne $offre = null;

    #[ORM\Column]
    private ?float $tarif = null;

    #[ORM\Column]
    private ?bool $abonnementActif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(['DOWNGRADE', 'UPGRADE', 'STATIC'])]
    private ?string $tag = 'STATIC';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomOuSociete = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numeroTva = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reccuring_payment_id = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reccuring_payment_payin_id = null;

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

    public function getOffre(): ?OffreInterne
    {
        return $this->offre;
    }

    public function setOffre(?OffreInterne $offre): static
    {
        $this->offre = $offre;

        return $this;
    }

    public function getTarif(): ?float
    {
        return $this->tarif;
    }

    public function setTarif(float $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function isAbonnementActif(): ?bool
    {
        return $this->abonnementActif;
    }

    public function setAbonnementActif(bool $abonnementActif): static
    {
        $this->abonnementActif = $abonnementActif;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getNomOuSociete(): ?string
    {
        return $this->nomOuSociete;
    }

    public function setNomOuSociete(?string $nomOuSociete): static
    {
        $this->nomOuSociete = $nomOuSociete;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
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

    public function getReccuringPaymentId(): ?string
    {
        return $this->reccuring_payment_id;
    }

    public function setReccuringPaymentId(?string $reccuring_payment_id): static
    {
        $this->reccuring_payment_id = $reccuring_payment_id;

        return $this;
    }

    public function getReccuringPaymentPayinId(): ?string
    {
        return $this->reccuring_payment_payin_id;
    }

    public function setReccuringPaymentPayinId(?string $reccuring_payment_payin_id): static
    {
        $this->reccuring_payment_payin_id = $reccuring_payment_payin_id;

        return $this;
    }
}
