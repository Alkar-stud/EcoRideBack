<?php

namespace App\Entity;

use App\Repository\RideRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RideRepository::class)]
class Ride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $startingAddress = null;

    #[ORM\Column(length: 255)]
    private ?string $arrivalAddress = null;

    #[ORM\Column]
    private ?DateTimeImmutable $startingAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $arrivalAt = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column]
    private ?int $nbPlacesAvailable = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $actualDepartureAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $actualArrivalAt = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'ridesDriver')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'ridesVehicle')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartingAddress(): ?string
    {
        return $this->startingAddress;
    }

    public function setStartingAddress(string $startingAddress): static
    {
        $this->startingAddress = $startingAddress;

        return $this;
    }

    public function getArrivalAddress(): ?string
    {
        return $this->arrivalAddress;
    }

    public function setArrivalAddress(string $arrivalAddress): static
    {
        $this->arrivalAddress = $arrivalAddress;

        return $this;
    }

    public function getStartingAt(): ?DateTimeImmutable
    {
        return $this->startingAt;
    }

    public function setStartingAt(DateTimeImmutable $startingAt): static
    {
        $this->startingAt = $startingAt;

        return $this;
    }

    public function getArrivalAt(): ?DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function setArrivalAt(DateTimeImmutable $arrivalAt): static
    {
        $this->arrivalAt = $arrivalAt;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getNbPlacesAvailable(): ?int
    {
        return $this->nbPlacesAvailable;
    }

    public function setNbPlacesAvailable(int $nbPlacesAvailable): static
    {
        $this->nbPlacesAvailable = $nbPlacesAvailable;

        return $this;
    }

    public function getActualDepartureAt(): ?DateTimeImmutable
    {
        return $this->actualDepartureAt;
    }

    public function setActualDepartureAt(?DateTimeImmutable $actualDepartureAt): static
    {
        $this->actualDepartureAt = $actualDepartureAt;

        return $this;
    }

    public function getActualArrivalAt(): ?DateTimeImmutable
    {
        return $this->actualArrivalAt;
    }

    public function setActualArrivalAt(?DateTimeImmutable $actualArrivalAt): static
    {
        $this->actualArrivalAt = $actualArrivalAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

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
