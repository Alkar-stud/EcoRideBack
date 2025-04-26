<?php

namespace App\Entity;

use App\Repository\NoticeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoticeRepository::class)]
class Notice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?int $grade = null;

    #[ORM\ManyToOne(inversedBy: 'noticesPublisher')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $publishedBy = null;

    #[ORM\ManyToOne(inversedBy: 'noticesDriver')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $relatedFor = null;

    #[ORM\ManyToOne(inversedBy: 'noticesToValidate')]
    private ?User $validateBy = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $validateAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?NoticeStatus $status = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function setGrade(int $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getPublishedBy(): ?User
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(?User $publishedBy): static
    {
        $this->publishedBy = $publishedBy;

        return $this;
    }

    public function getRelatedFor(): ?User
    {
        return $this->relatedFor;
    }

    public function setRelatedFor(?User $relatedFor): static
    {
        $this->relatedFor = $relatedFor;

        return $this;
    }

    public function getValidateBy(): ?User
    {
        return $this->validateBy;
    }

    public function setValidateBy(?User $validateBy): static
    {
        $this->validateBy = $validateBy;

        return $this;
    }

    public function getValidateAt(): ?DateTimeImmutable
    {
        return $this->validateAt;
    }

    public function setValidateAt(?DateTimeImmutable $validateAt): static
    {
        $this->validateAt = $validateAt;

        return $this;
    }

    public function getStatus(): ?NoticeStatus
    {
        return $this->status;
    }

    public function setStatus(?NoticeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
