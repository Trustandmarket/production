<?php

namespace App\Entity;

use App\Repository\WpPostsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: WpPostsRepository::class)]
#[ORM\Table(name: "wp_posts")]
#[ORM\Index(name: "type_status_date", columns: ["post_type", "post_status", "post_date", "id"])]
#[ORM\Index(name: "post_parent", columns: ["post_parent"])]
#[ORM\Index(name: "post_name", columns: ["post_name"])]
#[ORM\Index(name: "post_author", columns: ["post_author"])]
class WpPosts
{
    public function __construct()
    {
        $this->wpPostmeta = new ArrayCollection();
        $this->extTranslationWpPosts = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $id;

    #[ORM\Column(name: "post_author", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $postAuthor = '0';

    #[ORM\Column(name: "post_date", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $postDate = '2000-01-01 00:00:00';

    #[ORM\Column(name: "post_date_gmt", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $postDateGmt = '2000-01-01 00:00:00';

    #[ORM\Column(name: "post_content", type: "text", length: 0, nullable: false)]
    private $postContent;

    #[ORM\Column(name: "post_title", type: "text", length: 65535, nullable: false)]
    private $postTitle;

    #[ORM\Column(name: "post_excerpt", type: "text", length: 65535, nullable: false)]
    private $postExcerpt;

    #[ORM\Column(name: "post_status", type: "string", length: 20, nullable: false, options: ["default" => "publish"])]
    private $postStatus = 'publish';

    #[ORM\Column(name: "comment_status", type: "string", length: 20, nullable: false, options: ["default" => "open"])]
    private $commentStatus = 'open';

    #[ORM\Column(name: "ping_status", type: "string", length: 20, nullable: false, options: ["default" => "open"])]
    private $pingStatus = 'open';

    #[ORM\Column(name: "post_password", type: "string", length: 255, nullable: false)]
    private $postPassword = '';

    #[ORM\Column(name: "post_name", type: "string", length: 200, nullable: false)]
    private $postName = '';

    #[ORM\Column(name: "to_ping", type: "text", length: 65535, nullable: false)]
    private $toPing;

    #[ORM\Column(name: "pinged", type: "text", length: 65535, nullable: false)]
    private $pinged;

    #[ORM\Column(name: "post_modified", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $postModified = '2000-01-01 00:00:00';

    #[ORM\Column(name: "post_modified_gmt", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $postModifiedGmt = '2000-01-01 00:00:00';

    #[ORM\Column(name: "post_content_filtered", type: "text", length: 0, nullable: false)]
    private $postContentFiltered;

    #[ORM\Column(name: "post_parent", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $postParent = '0';

    #[ORM\Column(name: "guid", type: "string", length: 255, nullable: false)]
    private $guid = '';

    #[ORM\Column(name: "menu_order", type: "integer", nullable: false)]
    private $menuOrder = '0';

    #[ORM\Column(name: "post_type", type: "string", length: 20, nullable: false, options: ["default" => "post"])]
    private $postType ;

    #[ORM\Column(name: "post_mime_type", type: "string", length: 100, nullable: false)]
    private $postMimeType = '';

    #[ORM\Column(name: "comment_count", type: "bigint", nullable: false)]
    private $commentCount = '0';

    protected $termTaxonomyId;

    #[ORM\OneToMany(targetEntity: ExtTranslationWpPosts::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpPosts;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPostAuthor(): ?int
    {
        return $this->postAuthor;
    }

    public function setPostAuthor(int $postAuthor): self
    {
        $this->postAuthor = $postAuthor;

        return $this;
    }

    public function getPostDate(): ?DateTimeInterface
    {
        return $this->postDate;
    }

    public function setPostDate(DateTimeInterface $postDate): self
    {
        $this->postDate = $postDate;

        return $this;
    }

    public function getPostDateGmt(): ?DateTimeInterface
    {
        return $this->postDateGmt;
    }

    public function setPostDateGmt(DateTimeInterface $postDateGmt): self
    {
        $this->postDateGmt = $postDateGmt;

        return $this;
    }

    public function getPostContent(): ?string
    {
        return $this->postContent;
    }

    public function setPostContent(string $postContent): self
    {
        $this->postContent = $postContent;

        return $this;
    }

    public function getPostTitle(): ?string
    {
        return $this->postTitle;
    }

    public function setPostTitle(string $postTitle): self
    {
        $this->postTitle = $postTitle;

        return $this;
    }

    public function getPostExcerpt(): ?string
    {
        return $this->postExcerpt;
    }

    public function setPostExcerpt(string $postExcerpt): self
    {
        $this->postExcerpt = $postExcerpt;

        return $this;
    }

    public function getPostStatus(): ?string
    {
        return $this->postStatus;
    }

    public function setPostStatus(string $postStatus): self
    {
        $this->postStatus = $postStatus;

        return $this;
    }

    public function getCommentStatus(): ?string
    {
        return $this->commentStatus;
    }

    public function setCommentStatus(string $commentStatus): self
    {
        $this->commentStatus = $commentStatus;

        return $this;
    }

    public function getPingStatus(): ?string
    {
        return $this->pingStatus;
    }

    public function setPingStatus(string $pingStatus): self
    {
        $this->pingStatus = $pingStatus;

        return $this;
    }

    public function getPostPassword(): ?string
    {
        return $this->postPassword;
    }

    public function setPostPassword(string $postPassword): self
    {
        $this->postPassword = $postPassword;

        return $this;
    }

    public function getPostName(): ?string
    {
        return $this->postName;
    }

    public function setPostName(string $postName): self
    {
        $this->postName = $postName;

        return $this;
    }

    public function getToPing(): ?string
    {
        return $this->toPing;
    }

    public function setToPing(string $toPing): self
    {
        $this->toPing = $toPing;

        return $this;
    }

    public function getPinged(): ?string
    {
        return $this->pinged;
    }

    public function setPinged(string $pinged): self
    {
        $this->pinged = $pinged;

        return $this;
    }

    public function getPostModified(): ?DateTimeInterface
    {
        return $this->postModified;
    }

    public function setPostModified(DateTimeInterface $postModified): self
    {
        $this->postModified = $postModified;

        return $this;
    }

    public function getPostModifiedGmt(): ?DateTimeInterface
    {
        return $this->postModifiedGmt;
    }

    public function setPostModifiedGmt(DateTimeInterface $postModifiedGmt): self
    {
        $this->postModifiedGmt = $postModifiedGmt;

        return $this;
    }

    public function getPostContentFiltered(): ?string
    {
        return $this->postContentFiltered;
    }

    public function setPostContentFiltered(string $postContentFiltered): self
    {
        $this->postContentFiltered = $postContentFiltered;

        return $this;
    }

    public function getPostParent(): ?int
    {
        return $this->postParent;
    }

    public function setPostParent(int $postParent): self
    {
        $this->postParent = $postParent;

        return $this;
    }

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): self
    {
        $this->guid = $guid;

        return $this;
    }

    public function getMenuOrder(): ?int
    {
        return $this->menuOrder;
    }

    public function setMenuOrder(int $menuOrder): self
    {
        $this->menuOrder = $menuOrder;

        return $this;
    }

    public function getPostType(): ?string
    {
        return $this->postType;
    }

    public function setPostType(string $postType): self
    {
        $this->postType = $postType;

        return $this;
    }

    public function getPostMimeType(): ?string
    {
        return $this->postMimeType;
    }

    public function setPostMimeType(string $postMimeType): self
    {
        $this->postMimeType = $postMimeType;

        return $this;
    }

    public function getCommentCount(): ?string
    {
        return $this->commentCount;
    }

    public function setCommentCount(string $commentCount): self
    {
        $this->commentCount = $commentCount;

        return $this;
    }

//Pour la clé migrant dans la table wp_term_relationship

    public function getTermTaxonomyId(): ?int
    {
        return $this->termTaxonomyId;
    }

    public function setTermTaxonomyId(int $termTaxonomyId): self
    {
        $this->termTaxonomyId = $termTaxonomyId;

        return $this;
    }

    /**
     * @return Collection<int, ExtTranslationWpPosts>
     */
    public function getExtTranslationWpPosts(): Collection
    {
        return $this->extTranslationWpPosts;
    }

    public function addExtTranslationWpPost(ExtTranslationWpPosts $extTranslationWpPost): self
    {
        if (!$this->extTranslationWpPosts->contains($extTranslationWpPost)) {
            $this->extTranslationWpPosts[] = $extTranslationWpPost;
            $extTranslationWpPost->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpPost(ExtTranslationWpPosts $extTranslationWpPost): self
    {
        if ($this->extTranslationWpPosts->removeElement($extTranslationWpPost)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpPost->getForeignKey() === $this) {
                $extTranslationWpPost->setForeignKey(null);
            }
        }

        return $this;
    }

    public function getReservationPriceTtc(): ?string {
        if($this->toPing != ''){
            return $this->toPing;
        }else{
            return $this->postParent;
        }
    }
}
