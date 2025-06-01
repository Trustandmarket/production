<?php

namespace App\Entity;

use App\Repository\WpTermTaxonomyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Table(name: "wp_term_taxonomy")]
#[UniqueEntity(fields: ["term_id_taxonomy"], columns: ["term_id", "taxonomy"], message: "Ce departement existe deja.")]
#[ORM\Index(name: "taxonomy", columns: ["taxonomy"])]
#[ORM\Entity(repositoryClass: WpTermTaxonomyRepository::class)]
class WpTermTaxonomy
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "term_taxonomy_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $termTaxonomyId;

    #[ORM\Column(name: "term_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $termId = '0';

    #[ORM\Column(name: "taxonomy", type: "string", length: 32, nullable: false)]
    private $taxonomy = '';

    #[ORM\Column(name: "description", type: "text", length: 0, nullable: false)]
    private $description;

    #[ORM\Column(name: "parent", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $parent = '0';

    #[ORM\Column(name: "count", type: "bigint", nullable: false)]
    private $count = '0';


    /**
     * @var string
     */
    private $name = '';

    #[ORM\OneToMany(targetEntity: ExtTranslationWpTermTaxonomy::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpTermTaxonomies;

    public function __construct()
    {
        $this->extTranslationWpTermTaxonomies = new ArrayCollection();
    }

    public function getTermTaxonomyId(): ?int
    {
        return $this->termTaxonomyId;
    }

    public function getTermId(): ?int
    {
        return $this->termId;
    }

    public function setTermId(int $termId): self
    {
        $this->termId = $termId;

        return $this;
    }

    public function getTaxonomy(): ?string
    {
        return $this->taxonomy;
    }

    public function setTaxonomy(string $taxonomy): self
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function setParent(string $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, ExtTranslationWpTermTaxonomy>
     */
    public function getExtTranslationWpTermTaxonomies(): Collection
    {
        return $this->extTranslationWpTermTaxonomies;
    }

    public function addExtTranslationWpTermTaxonomy(ExtTranslationWpTermTaxonomy $extTranslationWpTermTaxonomy): self
    {
        if (!$this->extTranslationWpTermTaxonomies->contains($extTranslationWpTermTaxonomy)) {
            $this->extTranslationWpTermTaxonomies[] = $extTranslationWpTermTaxonomy;
            $extTranslationWpTermTaxonomy->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpTermTaxonomy(ExtTranslationWpTermTaxonomy $extTranslationWpTermTaxonomy): self
    {
        if ($this->extTranslationWpTermTaxonomies->removeElement($extTranslationWpTermTaxonomy)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpTermTaxonomy->getForeignKey() === $this) {
                $extTranslationWpTermTaxonomy->setForeignKey(null);
            }
        }

        return $this;
    }
}
