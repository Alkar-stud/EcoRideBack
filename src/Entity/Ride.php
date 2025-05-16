<?php

namespace App\Entity;

use App\Repository\RideRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RideRepository::class)]
class Ride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['ride_read'])]
    private ?string $startingAddress = null;

    #[ORM\Column(length: 255)]
    #[Groups(['ride_read'])]
    private ?string $arrivalAddress = null;

    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?DateTimeImmutable $startingAt = null;

    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?DateTimeImmutable $arrivalAt = null;

    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?int $price = null;

    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?int $nbPlacesAvailable = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $actualDepartureAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $actualArrivalAt = null;

    #[ORM\Column(length: 50)]
    #[Groups(['ride_read'])]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'ridesDriver')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $driver = null;

    #[ORM\ManyToOne(inversedBy: 'ridesVehicle')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ride_read'])]
    private ?Vehicle $vehicle = null;

    #[ORM\Column]
    #[Groups(['ride_read'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'ridesPassenger')]
    private Collection $passenger;

    public function __construct()
    {
        $this->passenger = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, User>
     */
    public function getPassenger(): Collection
    {
        return $this->passenger;
    }

    public function addPassenger(User $passenger): static
    {
        if (!$this->passenger->contains($passenger)) {
            $this->passenger->add($passenger);
        }

        return $this;
    }

    public function removePassenger(User $passenger): static
    {
        $this->passenger->removeElement($passenger);

        return $this;
    }
}
