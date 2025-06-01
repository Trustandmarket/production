<?php

namespace App\Entity;

use App\Repository\UserUniqueDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserUniqueDataRepository::class)]
class UserUniqueData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'userUniqueData', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCommercial = null;

    #[ORM\ManyToOne(inversedBy: 'userUniqueData')]
    private ?Departement $departement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $bdaytime = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $sexe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nationalityCountry = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $residenceCountry = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $billingEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $raisonSociale = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $postCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paysDomicile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numeroNomRueDomicile = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $codePostalDomicile = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $villeDomicile = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paysLivraison = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numeroNomRueLivraison = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $codePostalLivraison = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $villeLivraison = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $mp_user_card_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }


    public function getNomCommercial(): ?string
    {
        return $this->nomCommercial;
    }

    public function setNomCommercial(?string $nomCommercial): static
    {
        $this->nomCommercial = $nomCommercial;

        return $this;
    }

    public function getDepartement(): ?Departement
    {
        return $this->departement;
    }

    public function setDepartement(?Departement $departement): static
    {
        $this->departement = $departement;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getBdaytime(): ?string
    {
        return $this->bdaytime;
    }

    public function setBdaytime(?string $bdaytime): static
    {
        $this->bdaytime = $bdaytime;

        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;

        return $this;
    }

    public function getNationalityCountry(): ?string
    {
        return $this->nationalityCountry;
    }

    public function setNationalityCountry(?string $nationalityCountry): static
    {
        $this->nationalityCountry = $nationalityCountry;

        return $this;
    }

    public function getResidenceCountry(): ?string
    {
        return $this->residenceCountry;
    }

    public function setResidenceCountry(?string $residenceCountry): static
    {
        $this->residenceCountry = $residenceCountry;

        return $this;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): static
    {
        $this->billingEmail = $billingEmail;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(?string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
    }

    public function getPostCode(): ?string
    {
        return $this->postCode;
    }

    public function setPostCode(?string $postCode): static
    {
        $this->postCode = $postCode;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getPaysDomicile(): ?string
    {
        return $this->paysDomicile;
    }

    public function setPaysDomicile(?string $paysDomicile): static
    {
        $this->paysDomicile = $paysDomicile;

        return $this;
    }

    public function getNumeroNomRueDomicile(): ?string
    {
        return $this->numeroNomRueDomicile;
    }

    public function setNumeroNomRueDomicile(?string $numeroNomRueDomicile): static
    {
        $this->numeroNomRueDomicile = $numeroNomRueDomicile;

        return $this;
    }

    public function getCodePostalDomicile(): ?string
    {
        return $this->codePostalDomicile;
    }

    public function setCodePostalDomicile(?string $codePostalDomicile): static
    {
        $this->codePostalDomicile = $codePostalDomicile;

        return $this;
    }

    public function getVilleDomicile(): ?string
    {
        return $this->villeDomicile;
    }

    public function setVilleDomicile(?string $villeDomicile): static
    {
        $this->villeDomicile = $villeDomicile;

        return $this;
    }

    public function getPaysLivraison(): ?string
    {
        return $this->paysLivraison;
    }

    public function setPaysLivraison(?string $paysLivraison): static
    {
        $this->paysLivraison = $paysLivraison;

        return $this;
    }

    public function getNumeroNomRueLivraison(): ?string
    {
        return $this->numeroNomRueLivraison;
    }

    public function setNumeroNomRueLivraison(?string $numeroNomRueLivraison): static
    {
        $this->numeroNomRueLivraison = $numeroNomRueLivraison;

        return $this;
    }

    public function getCodePostalLivraison(): ?string
    {
        return $this->codePostalLivraison;
    }

    public function setCodePostalLivraison(?string $codePostalLivraison): static
    {
        $this->codePostalLivraison = $codePostalLivraison;

        return $this;
    }

    public function getVilleLivraison(): ?string
    {
        return $this->villeLivraison;
    }

    public function setVilleLivraison(?string $villeLivraison): static
    {
        $this->villeLivraison = $villeLivraison;

        return $this;
    }

    public function getMpUserCardId(): ?string
    {
        return $this->mp_user_card_id;
    }

    public function setMpUserCardId(?string $mp_user_card_id): static
    {
        $this->mp_user_card_id = $mp_user_card_id;

        return $this;
    }
}
