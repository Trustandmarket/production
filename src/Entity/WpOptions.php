<?php

namespace App\Entity;

use App\Repository\WpOptionsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Table(name: "wp_options")]
#[ORM\Entity(repositoryClass: WpOptionsRepository::class)]
#[UniqueEntity(fields: ["option_name"], message: "Cette option existe deja.")]
class WpOptions
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "option_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $optionId;

    #[ORM\Column(name: "option_name", type: "string", length: 191, nullable: false)]
    private $optionName = '';

    #[ORM\Column(name: "option_value", type: "text", length: 0, nullable: false)]
    private $optionValue;

    #[ORM\Column(name: "autoload", type: "string", length: 20, nullable: false, options: ["default" => "yes"])]
    private $autoload = 'yes';

    #[ORM\OneToMany(targetEntity: ExtTranslationWpOptions::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpOptions;

    public function __construct()
    {
        $this->extTranslationWpOptions = new ArrayCollection();
    }


    public function getOptionId(): ?string
    {
        return $this->optionId;
    }

    public function getOptionName(): ?string
    {
        return $this->optionName;
    }

    public function setOptionName(string $optionName): self
    {
        $this->optionName = $optionName;

        return $this;
    }

    public function getOptionValue(): ?string
    {
        return $this->optionValue;
    }

    public function setOptionValue(string $optionValue): self
    {
        $this->optionValue = $optionValue;

        return $this;
    }

    public function getAutoload(): ?string
    {
        return $this->autoload;
    }

    public function setAutoload(string $autoload): self
    {
        $this->autoload = $autoload;

        return $this;
    }

    /**
     * @return Collection<int, ExtTranslationWpOptions>
     */
    public function getExtTranslationWpOptions(): Collection
    {
        return $this->extTranslationWpOptions;
    }

    public function addExtTranslationWpOption(ExtTranslationWpOptions $extTranslationWpOption): self
    {
        if (!$this->extTranslationWpOptions->contains($extTranslationWpOption)) {
            $this->extTranslationWpOptions[] = $extTranslationWpOption;
            $extTranslationWpOption->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpOption(ExtTranslationWpOptions $extTranslationWpOption): self
    {
        if ($this->extTranslationWpOptions->removeElement($extTranslationWpOption)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpOption->getForeignKey() === $this) {
                $extTranslationWpOption->setForeignKey(null);
            }
        }

        return $this;
    }
}
