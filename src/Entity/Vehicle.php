<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_account', 'ride_read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $brand = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $model = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $color = null;

    #[ORM\Column(length: 25)]
    #[Groups(['user_account', 'ride_detail'])]
    private ?string $licensePlate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['user_account', 'ride_detail'])]
    private ?DateTime $licenseFirstDate = null;

    #[ORM\Column(length: 20)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $energy = null;

    #[ORM\Column]
    #[Groups(['user_account', 'ride_read'])]
    private ?int $maxNbPlacesAvailable = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "userVehicles")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Ride>
     */
    #[ORM\OneToMany(targetEntity: Ride::class, mappedBy: 'vehicle')]
    private Collection $ridesVehicle;

    public function __construct()
    {
        $this->ridesVehicle = new ArrayCollection();
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
        $this->brand = $brand;

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

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): static
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getLicenseFirstDate(): ?DateTime
    {
        return $this->licenseFirstDate;
    }

    public function setLicenseFirstDate(DateTime $licenseFirstDate): static
    {
        $this->licenseFirstDate = $licenseFirstDate;

        return $this;
    }

    public function getEnergy(): ?string
    {
        return $this->energy;
    }

    public function setEnergy(string $energy): static
    {
        $this->energy = $energy;

        return $this;
    }

    public function getMaxNbPlacesAvailable(): ?int
    {
        return $this->maxNbPlacesAvailable;
    }

    public function setMaxNbPlacesAvailable(int $maxNbPlacesAvailable): static
    {
        $this->maxNbPlacesAvailable = $maxNbPlacesAvailable;

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

    /**
     * @return Collection<int, Ride>
     */
    public function getRidesVehicle(): Collection
    {
        return $this->ridesVehicle;
    }

    public function addRidesVehicle(Ride $ridesVehicle): static
    {
        if (!$this->ridesVehicle->contains($ridesVehicle)) {
            $this->ridesVehicle->add($ridesVehicle);
            $ridesVehicle->setVehicle($this);
        }

        return $this;
    }

    public function removeRidesVehicle(Ride $ridesVehicle): static
    {
        if ($this->ridesVehicle->removeElement($ridesVehicle)) {
            // set the owning side to null (unless already changed)
            if ($ridesVehicle->getVehicle() === $this) {
                $ridesVehicle->setVehicle(null);
            }
        }

        return $this;
    }
}
