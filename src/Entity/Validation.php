<?php

namespace App\Entity;

use App\Repository\ValidationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ValidationRepository::class)]
class Validation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ride_control'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['ride_control'])]
    private ?bool $isAllOk = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ride_control'])]
    private ?User $passenger = null;

    #[ORM\ManyToOne(inversedBy: 'validations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ride $ride = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['ride_control'])]
    private ?string $content = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ride_control'])]
    private ?User $supportBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ride_control'])]
    private ?bool $isClosed = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ride_control'])]
    private ?User $closedBy = null;

    #[ORM\Column]
    #[Groups(['ride_control'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ride_control'])]
    private ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isAllOk(): ?bool
    {
        return $this->isAllOk;
    }

    public function setIsAllOk(bool $isAllOk): static
    {
        $this->isAllOk = $isAllOk;

        return $this;
    }

    public function getPassenger(): ?User
    {
        return $this->passenger;
    }

    public function setPassenger(?User $passenger): static
    {
        $this->passenger = $passenger;

        return $this;
    }

    public function getRide(): ?Ride
    {
        return $this->ride;
    }

    public function setRide(?Ride $ride): static
    {
        $this->ride = $ride;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getSupportBy(): ?User
    {
        return $this->supportBy;
    }

    public function setSupportBy(?User $supportBy): static
    {
        $this->supportBy = $supportBy;

        return $this;
    }

    public function isClosed(): ?bool
    {
        return $this->isClosed;
    }

    public function setIsClosed(?bool $isClosed): static
    {
        $this->isClosed = $isClosed;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

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
