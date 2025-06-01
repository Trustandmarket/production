<?php

namespace App\Entity;

use App\Entity\Traits\EntityTimestampableTrait;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: "departements")]
#[UniqueEntity(fields: ["departement_slug"], message: "Ce departement existe deja.")]
class Departement
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 255)]
    private $region;

    #[ORM\Column(type: "string", length: 255)]
    private $departement;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    #[Gedmo\Slug(fields: ["departement"])]
    private $departement_slug;

    #[ORM\OneToMany(mappedBy: 'departement', targetEntity: UserUniqueData::class)]
    private Collection $userUniqueData;

    public function __construct()
    {
        $this->userUniqueData = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function setNom(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(string $departement): self
    {
        $this->departement = $departement;

        return $this;
    }

    public function getDepartementSlug(): ?string
    {
        return $this->departement_slug;
    }

    public function setDepartementSlug(string $departement_slug): self
    {
        $this->departement_slug = $departement_slug;

        return $this;
    }

   /**
    * @return Collection<int, UserUniqueData>
    */
   public function getUserUniqueData(): Collection
   {
       return $this->userUniqueData;
   }

   public function addUserUniqueData(UserUniqueData $userUniqueData): static
   {
       if (!$this->userUniqueData->contains($userUniqueData)) {
           $this->userUniqueData->add($userUniqueData);
           $userUniqueData->setDepartement($this);
       }

       return $this;
   }

   public function removeUserUniqueData(UserUniqueData $userUniqueData): static
   {
       if ($this->userUniqueData->removeElement($userUniqueData)) {
           // set the owning side to null (unless already changed)
           if ($userUniqueData->getDepartement() === $this) {
               $userUniqueData->setDepartement(null);
           }
       }

       return $this;
   }
}
