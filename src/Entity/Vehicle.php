<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vehicle_basic', 'vehicle_details'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['vehicle_basic', 'vehicle_details'])]
    private ?string $brand = null;

    #[ORM\Column(length: 255)]
    #[Groups(['vehicle_details'])]
    private ?string $model = null;

    #[ORM\Column(length: 50)]
    #[Groups(['vehicle_details'])]
    private ?string $color = null;

    #[ORM\Column(length: 20)]
    #[Groups(['vehicle_details'])]
    private ?string $registration = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['vehicle_details'])]
    private ?DateTimeInterface $registrationFirstDate = null;

    #[ORM\Column]
    #[Groups(['vehicle_basic', 'vehicle_details'])]
    private ?int $nbPlace = null;

    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['vehicle_details'])]
    private ?User $owner = null;

    #[Groups(['vehicle_details'])]
    public function getOwnerId(): ?int
    {
        return $this->owner?->getId();
    }

    #[ORM\Column]
    #[Groups(['vehicle_details'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['vehicle_details'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['vehicle_details'])]
    private ?Energy $energy = null;

    #[Groups(['vehicle_details'])]
    public function getEnergyId(): ?int
    {
        return $this->energy?->getId();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): static
    {
        // Convertir la marque en majuscules
        $this->brand = strtoupper($brand);

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getRegistration(): ?string
    {
        return $this->registration;
    }

    public function setRegistration(string $registration): static
    {
        $this->registration = $registration;

        return $this;
    }

    public function getRegistrationFirstDate(): ?DateTimeInterface
    {
        return $this->registrationFirstDate;
    }

    public function setRegistrationFirstDate(\DateTimeInterface $registrationFirstDate): static
    {
        // Vérifier si la date est valide
        if (!$registrationFirstDate) {
            throw new \InvalidArgumentException('La date de première immatriculation doit être valide.');
        }

        $this->registrationFirstDate = $registrationFirstDate;

        return $this;
    }

    public function getNbPlace(): ?int
    {
        return $this->nbPlace;
    }

    public function setNbPlace(int $nbPlace): static
    {
        $this->nbPlace = $nbPlace;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    public function getEnergy(): ?Energy
    {
        return $this->energy;
    }

    public function setEnergy(?Energy $energy): static
    {
        $this->energy = $energy;

        return $this;
    }
}
