<?php

namespace App\Entity;

use App\Repository\WpPostmetaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "wp_postmeta")]
#[ORM\Index(name: "meta_key", columns: ["meta_key"])]
#[ORM\Index(name: "post_id", columns: ["post_id"])]
#[ORM\Entity(repositoryClass: WpPostmetaRepository::class)]
class WpPostmeta
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "meta_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $metaId;

    #[ORM\Column(name: "post_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    #[ORM\ManyToOne(targetEntity: "App\Entity\WpPosts")]
    private $postId = '0';

    #[ORM\Column(name: "meta_key", type: "string", length: 255, nullable: true)]
    private $metaKey;

    #[ORM\Column(name: "meta_value", type: "text", length: 0, nullable: true)]
    private $metaValue;

    #[ORM\OneToMany(targetEntity: ExtTranslationWpPostmeta::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpPostmetas;

    public function __construct()
    {
        $this->extTranslationWpPostmetas = new ArrayCollection();
    }


    public function getMetaId(): ?string
    {
        return $this->metaId;
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }

    public function setPostId(int $postId): self
    {
        $this->postId = $postId;

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
     * @return Collection<int, ExtTranslationWpPostmeta>
     */
    public function getExtTranslationWpPostmetas(): Collection
    {
        return $this->extTranslationWpPostmetas;
    }

    public function addExtTranslationWpPostmeta(ExtTranslationWpPostmeta $extTranslationWpPostmeta): self
    {
        if (!$this->extTranslationWpPostmetas->contains($extTranslationWpPostmeta)) {
            $this->extTranslationWpPostmetas[] = $extTranslationWpPostmeta;
            $extTranslationWpPostmeta->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpPostmeta(ExtTranslationWpPostmeta $extTranslationWpPostmeta): self
    {
        if ($this->extTranslationWpPostmetas->removeElement($extTranslationWpPostmeta)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpPostmeta->getForeignKey() === $this) {
                $extTranslationWpPostmeta->setForeignKey(null);
            }
        }

        return $this;
    }
}
