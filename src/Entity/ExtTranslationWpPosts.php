<?php

namespace App\Entity;

use App\Repository\ExtTranslationWpPostsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExtTranslationWpPostsRepository::class)]
#[ORM\Table(name: "ext_translations_wp_posts")]
class ExtTranslationWpPosts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 8)]
    private $locale;

    #[ORM\Column(type: "string", length: 190)]
    private $object_class;

    #[ORM\Column(type: "string", length: 32)]
    private $field;

    #[ORM\ManyToOne(targetEntity: WpPosts::class, inversedBy: "extTranslationWpPosts")]
    #[ORM\JoinColumn(nullable: true, name: "foreign_key", referencedColumnName: "id")]
    private $foreign_key;

    #[ORM\Column(type: "text", nullable: true)]
    private $content;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getObjectClass(): ?string
    {
        return $this->object_class;
    }

    public function setObjectClass(string $object_class): self
    {
        $this->object_class = $object_class;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getForeignKey(): ?WpPosts
    {
        return $this->foreign_key;
    }

    public function setForeignKey(?WpPosts $foreign_key): self
    {
        $this->foreign_key = $foreign_key;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
