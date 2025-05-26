<?php
// src/Document/MongoRideNotice.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "RidesNotices")]
class MongoRideNotice
{
    /**
     * @var string|null
     */
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "int")]
    private ?int $grade;

    #[MongoDB\Field(type: "string")]
    private ?string $title;

    #[MongoDB\Field(type: "string")]
    private ?string $content;

    #[MongoDB\Field(type: "int")]
    private ?int $publishedBy;

    #[MongoDB\Field(type: "int")]
    private ?int $validateBy;

    #[MongoDB\Field(type: "int")]
    private ?int $refusedBy;

    #[MongoDB\Field(type: "int")]
    private ?int $relatedFor;

    /**
     * @var \DateTimeInterface|null
     */
    #[MongoDB\Field(type: "date")]
    private $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    #[MongoDB\Field(type: "date")]
    private $updatedAt;


    public function getId()
    {
        return $this->id;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function setGrade(?int $grade): self
    {
        $this->grade = $grade;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
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

    public function getPublishedBy(): ?int
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(?int $publishedBy): self
    {
        $this->publishedBy = $publishedBy;
        return $this;
    }

    public function getValidateBy(): ?int
    {
        return $this->validateBy;
    }

    public function setValidateBy(?int $validateBy): self
    {
        $this->validateBy = $validateBy;
        return $this;
    }

    public function getRefusedBy(): ?int
    {
        return $this->refusedBy;
    }

    public function refusedBy(?int $refusedBy): self
    {
        $this->refusedBy = $refusedBy;
        return $this;
    }

    public function getRelatedFor(): ?int
    {
        return $this->relatedFor;
    }

    public function setRelatedFor(?int $relatedFor): self
    {
        $this->relatedFor = $relatedFor;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt($createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }



}