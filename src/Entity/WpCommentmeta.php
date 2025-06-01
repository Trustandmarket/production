<?php

namespace App\Entity;

use App\Repository\WpCommentmetaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WpCommentmetaRepository::class)]
class WpCommentmeta
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "meta_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $metaId;

    #[ORM\ManyToOne(targetEntity: WpComments::class)]
    #[ORM\Column(name: "comment_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $commentId = '0';

    #[ORM\Column(name: "meta_key", type: "string", length: 255, nullable: true)]
    private $metaKey;

    #[ORM\Column(name: "meta_value", type: "text", length: 0, nullable: true)]
    private $metaValue;

    #[ORM\OneToMany(targetEntity: ExtTranslationWpCommentMeta::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpCommentMetas;

    public function __construct()
    {
        $this->extTranslationWpCommentMetas = new ArrayCollection();
    }

    public function getMetaId(): ?string
    {
        return $this->metaId;
    }

    public function getCommentId(): ?string
    {
        return $this->commentId;
    }

    public function setCommentId(string $commentId): self
    {
        $this->commentId = $commentId;

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
     * @return Collection<int, ExtTranslationWpCommentMeta>
     */
    public function getExtTranslationWpCommentMetas(): Collection
    {
        return $this->extTranslationWpCommentMetas;
    }

    public function addExtTranslationWpCommentMeta(ExtTranslationWpCommentMeta $extTranslationWpCommentMeta): self
    {
        if (!$this->extTranslationWpCommentMetas->contains($extTranslationWpCommentMeta)) {
            $this->extTranslationWpCommentMetas[] = $extTranslationWpCommentMeta;
            $extTranslationWpCommentMeta->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpCommentMeta(ExtTranslationWpCommentMeta $extTranslationWpCommentMeta): self
    {
        if ($this->extTranslationWpCommentMetas->removeElement($extTranslationWpCommentMeta)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpCommentMeta->getForeignKey() === $this) {
                $extTranslationWpCommentMeta->setForeignKey(null);
            }
        }

        return $this;
    }
}
