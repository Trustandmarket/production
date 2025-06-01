<?php

namespace App\Entity;

use App\Repository\WpTermsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "wp_terms")]
#[ORM\Index(name: "name", columns: ["name"])]
#[ORM\Index(name: "slug", columns: ["slug"])]
#[ORM\Entity(repositoryClass: WpTermsRepository::class)]
class WpTerms
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "term_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $termId;

    #[ORM\Column(name: "name", type: "string", length: 200, nullable: false)]
    private $name = '';

    #[ORM\Column(name: "slug", type: "string", length: 200, nullable: false)]
    private $slug = '';

    #[ORM\Column(name: "term_group", type: "bigint", nullable: false)]
    private $termGroup = '0';

    #[ORM\Column(name: "term_price", type: "string", length: 100, nullable: false)]
    private $termPrice = '0';

    #[ORM\Column(name: "term_icon", type: "string", length: 255, nullable: false)]
    private $termIcon = '';

    #[ORM\OneToMany(targetEntity: ExtTranslationWpTerms::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpTerms;

    #[ORM\OneToMany(targetEntity: WpTermTaxonomy::class, mappedBy: "term")]
    private $wpTermTaxonomies;


    public function __construct()
    {
        $this->extTranslationWpTerms = new ArrayCollection();
        $this->wpTermTaxonomies = new ArrayCollection();
    }

    public function getTermId(): ?int
    {
        return $this->termId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTermGroup(): ?string
    {
        return $this->termGroup;
    }

    public function setTermGroup(string $termGroup): self
    {
        $this->termGroup = $termGroup;

        return $this;
    }

    public function getTermPrice(): ?string
    {
        return $this->termPrice;
    }

    public function setTermPrice(string $termPrice): self
    {
        $this->termPrice = $termPrice;

        return $this;
    }

    public function getTermIcon(): ?string
    {
        return $this->termIcon;
    }

    public function setTermIcon(string $termIcon): self
    {
        $this->termIcon = $termIcon;

        return $this;
    }

    /**
     * @return Collection<int, ExtTranslationWpTerms>
     */
    public function getExtTranslationWpTerms(): Collection
    {
        return $this->extTranslationWpTerms;
    }

    public function addExtTranslationWpTerm(ExtTranslationWpTerms $extTranslationWpTerm): self
    {
        if (!$this->extTranslationWpTerms->contains($extTranslationWpTerm)) {
            $this->extTranslationWpTerms[] = $extTranslationWpTerm;
            $extTranslationWpTerm->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpTerm(ExtTranslationWpTerms $extTranslationWpTerm): self
    {
        if ($this->extTranslationWpTerms->removeElement($extTranslationWpTerm)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpTerm->getForeignKey() === $this) {
                $extTranslationWpTerm->setForeignKey(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WpTermTaxonomy>
     */
    public function getWpTermTaxonomies(): Collection
    {
        return $this->wpTermTaxonomies;
    }

    public function addWpTermTaxonomy(WpTermTaxonomy $wpTermTaxonomy): self
    {
        if (!$this->wpTermTaxonomies->contains($wpTermTaxonomy)) {
            $this->wpTermTaxonomies[] = $wpTermTaxonomy;
            $wpTermTaxonomy->setTerm($this);
        }

        return $this;
    }

    public function removeWpTermTaxonomy(WpTermTaxonomy $wpTermTaxonomy): self
    {
        if ($this->wpTermTaxonomies->removeElement($wpTermTaxonomy)) {
            // set the owning side to null (unless already changed)
            if ($wpTermTaxonomy->getTerm() === $this) {
                $wpTermTaxonomy->setTerm(null);
            }
        }

        return $this;
    }
}
