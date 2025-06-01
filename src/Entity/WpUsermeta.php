<?php

namespace App\Entity;

use App\Repository\WpUsermetaRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Table(name: "wp_usermeta")]
#[ORM\Index(name: "meta_key", columns: ["meta_key"])]
#[ORM\Index(name: "user_id", columns: ["user_id"])]
#[ORM\Entity(repositoryClass: WpUsermetaRepository::class)]
class WpUsermeta
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "umeta_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $umetaId;

    #[ORM\Column(name: "user_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $userId = '0';

    #[ORM\Column(name: "meta_key", type: "string", length: 255, nullable: true)]
    private $metaKey;

    #[ORM\Column(name: "meta_value", type: "text", length: 0, nullable: true)]
    private $metaValue;

    #[ORM\OneToMany(targetEntity: ExtTranslationWpUsermeta::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpUsermetas;

    public function __construct()
    {
        $this->extTranslationWpUsermetas = new ArrayCollection();
    }


    public function getUmetaId(): ?int
    {
        return $this->umetaId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getMetaKey(): ?string
    {
        return $this->metaKey;
    }

    public function setMetaKey(?string $metaKey): self
    {
        $this->metaKey = $metaKey;

        return $this;
    }

    public function getMetaValue(): ?string
    {
        return $this->metaValue;
    }

    public function setMetaValue(?string $metaValue): self
    {
        $this->metaValue = $metaValue;

        return $this;
    }

    /**
     * @return Collection<int, ExtTranslationWpUsermeta>
     */
    public function getExtTranslationWpUsermetas(): Collection
    {
        return $this->extTranslationWpUsermetas;
    }

    public function addExtTranslationWpUsermeta(ExtTranslationWpUsermeta $extTranslationWpUsermeta): self
    {
        if (!$this->extTranslationWpUsermetas->contains($extTranslationWpUsermeta)) {
            $this->extTranslationWpUsermetas[] = $extTranslationWpUsermeta;
            $extTranslationWpUsermeta->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpUsermeta(ExtTranslationWpUsermeta $extTranslationWpUsermeta): self
    {
        if ($this->extTranslationWpUsermetas->removeElement($extTranslationWpUsermeta)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpUsermeta->getForeignKey() === $this) {
                $extTranslationWpUsermeta->setForeignKey(null);
            }
        }

        return $this;
    }
}
