<?php

namespace App\Entity;

use App\Repository\WpCommentsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WpCommentsRepository::class)]
#[ORM\Table(name: "wp_comments")]
#[ORM\Index(name: "comment_approved_date_gmt", columns: ["comment_approved", "comment_date_gmt"])]
#[ORM\Index(name: "comment_date_gmt", columns: ["comment_date_gmt"])]
#[ORM\Index(name: "comment_author_email", columns: ["comment_author_email"])]
#[ORM\Index(name: "comment_post_ID", columns: ["comment_post_ID"])]
#[ORM\Index(name: "comment_parent", columns: ["comment_parent"])]
#[ORM\Index(name: "woo_idx_comment_type", columns: ["comment_type"])]
class WpComments
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "comment_ID", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $commentId;

    #[ORM\ManyToOne(targetEntity: WpPosts::class)]
    #[ORM\Column(name: "comment_post_ID", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $commentPostId = '0';

    #[ORM\Column(name: "comment_author", type: "text", length: 255, nullable: false)]
    private $commentAuthor;

    #[ORM\Column(name: "comment_author_email", type: "string", length: 100, nullable: false)]
    private $commentAuthorEmail = '';

    #[ORM\Column(name: "comment_author_url", type: "string", length: 200, nullable: false)]
    private $commentAuthorUrl = '';

    #[ORM\Column(name: "comment_author_IP", type: "string", length: 100, nullable: false)]
    private $commentAuthorIp = '';

    #[ORM\Column(name: "comment_date", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $commentDate = '2000-01-01 00:00:00';

    #[ORM\Column(name: "comment_date_gmt", type: "datetime", nullable: false, options: ["default" => "2000-01-01 00:00:00"])]
    private $commentDateGmt = '2000-01-01 00:00:00';

    #[ORM\Column(name: "comment_content", type: "text", length: 65535, nullable: false)]
    private $commentContent;

    #[ORM\Column(name: "comment_karma", type: "integer", nullable: false)]
    private $commentKarma = '0';

    #[ORM\Column(name: "comment_approved", type: "string", length: 20, nullable: false, options: ["default" => "1"])]
    private $commentApproved = '1';

    #[ORM\Column(name: "comment_agent", type: "string", length: 255, nullable: false)]
    private $commentAgent = '';

    #[ORM\Column(name: "comment_type", type: "string", length: 20, nullable: false)]
    private $commentType = '';

    #[ORM\Column(name: "comment_parent", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $commentParent = '0';

    #[ORM\Column(name: "user_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $userId = '0';

    #[ORM\OneToMany(targetEntity: ExtTranslationWpComments::class, mappedBy: "foreign_key", orphanRemoval: true)]
    private $extTranslationWpComments;

    public function __construct()
    {
        $this->extTranslationWpComments = new ArrayCollection();
    }


    public function getCommentId(): ?string
    {
        return $this->commentId;
    }

    public function getCommentPostId(): ?string
    {
        return $this->commentPostId;
    }

    public function setCommentPostId(string $commentPostId): self
    {
        $this->commentPostId = $commentPostId;

        return $this;
    }

    public function getCommentAuthor(): ?string
    {
        return $this->commentAuthor;
    }

    public function setCommentAuthor(string $commentAuthor): self
    {
        $this->commentAuthor = $commentAuthor;

        return $this;
    }

    public function getCommentAuthorEmail(): ?string
    {
        return $this->commentAuthorEmail;
    }

    public function setCommentAuthorEmail(string $commentAuthorEmail): self
    {
        $this->commentAuthorEmail = $commentAuthorEmail;

        return $this;
    }

    public function getCommentAuthorUrl(): ?string
    {
        return $this->commentAuthorUrl;
    }

    public function setCommentAuthorUrl(string $commentAuthorUrl): self
    {
        $this->commentAuthorUrl = $commentAuthorUrl;

        return $this;
    }

    public function getCommentAuthorIp(): ?string
    {
        return $this->commentAuthorIp;
    }

    public function setCommentAuthorIp(string $commentAuthorIp): self
    {
        $this->commentAuthorIp = $commentAuthorIp;

        return $this;
    }

    public function getCommentDate(): ?DateTimeInterface
    {
        return $this->commentDate;
    }

    public function setCommentDate(DateTime $commentDate): self
    {
        $this->commentDate = $commentDate;

        return $this;
    }

    public function getCommentDateGmt(): ?DateTimeInterface
    {
        return $this->commentDateGmt;
    }

    public function setCommentDateGmt(DateTime $commentDateGmt): self
    {
        $this->commentDateGmt = $commentDateGmt;

        return $this;
    }

    public function getCommentContent(): ?string
    {
        return $this->commentContent;
    }

    public function setCommentContent(string $commentContent): self
    {
        $this->commentContent = $commentContent;

        return $this;
    }

    public function getCommentKarma(): ?int
    {
        return $this->commentKarma;
    }

    public function setCommentKarma(int $commentKarma): self
    {
        $this->commentKarma = $commentKarma;

        return $this;
    }

    public function getCommentApproved(): ?string
    {
        return $this->commentApproved;
    }

    public function setCommentApproved(string $commentApproved): self
    {
        $this->commentApproved = $commentApproved;

        return $this;
    }

    public function getCommentAgent(): ?string
    {
        return $this->commentAgent;
    }

    public function setCommentAgent(string $commentAgent): self
    {
        $this->commentAgent = $commentAgent;

        return $this;
    }

    public function getCommentType(): ?string
    {
        return $this->commentType;
    }

    public function setCommentType(string $commentType): self
    {
        $this->commentType = $commentType;

        return $this;
    }

    public function getCommentParent(): ?string
    {
        return $this->commentParent;
    }

    public function setCommentParent(string $commentParent): self
    {
        $this->commentParent = $commentParent;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getPost(): ?WpPosts
    {
        return $this->post;
    }

    public function setPost(?WpPosts $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return Collection<int, ExtTranslationWpComments>
     */
    public function getExtTranslationWpComments(): Collection
    {
        return $this->extTranslationWpComments;
    }

    public function addExtTranslationWpComment(ExtTranslationWpComments $extTranslationWpComment): self
    {
        if (!$this->extTranslationWpComments->contains($extTranslationWpComment)) {
            $this->extTranslationWpComments[] = $extTranslationWpComment;
            $extTranslationWpComment->setForeignKey($this);
        }

        return $this;
    }

    public function removeExtTranslationWpComment(ExtTranslationWpComments $extTranslationWpComment): self
    {
        if ($this->extTranslationWpComments->removeElement($extTranslationWpComment)) {
            // set the owning side to null (unless already changed)
            if ($extTranslationWpComment->getForeignKey() === $this) {
                $extTranslationWpComment->setForeignKey(null);
            }
        }

        return $this;
    }
}
