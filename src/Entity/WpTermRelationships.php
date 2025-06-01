<?php

namespace App\Entity;

use App\Repository\WpTermRelationshipsRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: "wp_term_relationships")]
#[ORM\Index(name: "term_taxonomy_id", columns: ["term_taxonomy_id"])]
#[ORM\Entity(repositoryClass: WpTermRelationshipsRepository::class)]
class WpTermRelationships
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "NONE")]
    #[ORM\Column(name: "object_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $objectId = '0';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "NONE")]
    #[ORM\Column(name: "term_taxonomy_id", type: "bigint", nullable: false, options: ["unsigned" => true])]
    private $termTaxonomyId = '0';

    #[ORM\Column(name: "term_order", type: "integer", nullable: false)]
    private $termOrder = '0';

    public function getObjectId(): ?int
    {
        return $this->objectId;
    }

    public function setObjectId(int $objectId): self
    {
        $this->objectId = $objectId;
        return $this;
    }

    public function setTermTaxonomyId(int $termTaxonomyId): self
    {
        $this->termTaxonomyId = $termTaxonomyId;
        return $this;
    }

    public function getTermTaxonomyId(): ?int
    {
        return $this->termTaxonomyId;
    }

    public function getTermOrder(): ?int
    {
        return $this->termOrder;
    }

    public function setTermOrder(int $termOrder): self
    {
        $this->termOrder = $termOrder;

        return $this;
    }
}
