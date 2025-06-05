<?php
// src/Document/MongoValidationHistory.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "ValidationHistoryContent")]
class MongoValidationHistory
{
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "int")]
    private ?int $rideId;

    #[MongoDB\Field(type: "int")]
    private ?int $validationId;

    #[MongoDB\Field(type: "int")]
    private ?int $user;

    #[MongoDB\Field(type: "string")]
    private ?string $addContent;

    #[MongoDB\Field(type: "boolean")]
    private ?bool $isClosed;

    #[MongoDB\Field(type: "int")]
    private ?int $closedBy;

    #[MongoDB\Field(type: "date")]
    private $createdAt;


    public function getId()
    {
        return $this->id;
    }

    public function getRideId(): ?int
    {
        return $this->rideId;
    }

    public function setRideId(int $rideId): self
    {
        $this->rideId = $rideId;
        return $this;
    }

    public function getValidationId(): ?int
    {
        return $this->validationId;
    }

    public function setValidationId(int $validationId): self
    {
        $this->validationId = $validationId;
        return $this;
    }

    public function getUser(): ?int
    {
        return $this->user;
    }

    public function setUser(int $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAddContent(): ?string
    {
        return $this->addContent;
    }

    public function setAddContent(?string $addContent): self
    {
        $this->addContent = $addContent;
        return $this;
    }

    public function getIsClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(?bool $isClosed): self
    {
        $this->isClosed = $isClosed;
        return $this;
    }

    public function getClosedBy(): ?int
    {
        return $this->closedBy;
    }

    public function setClosedBy(int $closedBy): self
    {
        $this->closedBy = $closedBy;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt( $createdDate): self
    {
        $this->createdAt = $createdDate;
        return $this;
    }

}
