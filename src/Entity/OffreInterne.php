<?php

namespace App\Entity;

use App\Repository\OffreInterneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;
use App\Entity\Traits\EntityTimestampableTrait;

#[ORM\Entity(repositoryClass: OffreInterneRepository::class)]
class OffreInterne
{
    use EntityTimestampableTrait;

    public function __toString(): string
    {
        return $this->getTitre();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Gedmo\Translatable]
    #[ORM\Column(length: 180)]
    private ?string $titre = null;

    #[Gedmo\Translatable]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptif = null;

    #[ORM\Column]
    private ?float $tarif = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Gedmo\Slug(fields: ['titre'])]
    private $slug;

    /**
     * Used locale to override Translation listener`s locale
     * this is not a mapped field of entity metadata, just a simple property
     */
    #[Gedmo\Locale]
    private $locale;

    #[ORM\OneToMany(mappedBy: 'offre', targetEntity: Abonnement::class, orphanRemoval: true)]
    private Collection $abonnements;

    #[ORM\Column(nullable: true)]
    private ?bool $creerAnnonce = true;

    #[ORM\Column(nullable: true)]
    private ?bool $creerDevis = false;

    #[ORM\Column(nullable: true)]
    private ?bool $referencement = true;

    #[ORM\Column(nullable: true)]
    private ?bool $packsProduction = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $enteteDescriptif = null;

    public function __construct()
    {
        $this->abonnements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescriptif(): ?string
    {
        return $this->descriptif;
    }

    public function setDescriptif(?string $descriptif): static
    {
        $this->descriptif = $descriptif;

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

    public function getSlug()
    {
        return $this->slug;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return Collection<int, Abonnement>
     */
    public function getAbonnements(): Collection
    {
        return $this->abonnements;
    }

    public function addAbonnement(Abonnement $abonnement): static
    {
        if (!$this->abonnements->contains($abonnement)) {
            $this->abonnements->add($abonnement);
            $abonnement->setOffre($this);
        }

        return $this;
    }

    public function removeAbonnement(Abonnement $abonnement): static
    {
        if ($this->abonnements->removeElement($abonnement)) {
            // set the owning side to null (unless already changed)
            if ($abonnement->getOffre() === $this) {
                $abonnement->setOffre(null);
            }
        }

        return $this;
    }

    public function isCreerAnnonce(): ?bool
    {
        return $this->creerAnnonce;
    }

    public function setCreerAnnonce(?bool $creerAnnonce): static
    {
        $this->creerAnnonce = $creerAnnonce;

        return $this;
    }

    public function isCreerDevis(): ?bool
    {
        return $this->creerDevis;
    }

    public function setCreerDevis(?bool $creerDevis): static
    {
        $this->creerDevis = $creerDevis;

        return $this;
    }

    public function isReferencement(): ?bool
    {
        return $this->referencement;
    }

    public function setReferencement(?bool $referencement): static
    {
        $this->referencement = $referencement;

        return $this;
    }

    public function isPacksProduction(): ?bool
    {
        return $this->packsProduction;
    }

    public function setPacksProduction(?bool $packsProduction): static
    {
        $this->packsProduction = $packsProduction;

        return $this;
    }

    public function getEnteteDescriptif(): ?string
    {
        return $this->enteteDescriptif;
    }

    public function setEnteteDescriptif(?string $enteteDescriptif): static
    {
        $this->enteteDescriptif = $enteteDescriptif;

        return $this;
    }
}
