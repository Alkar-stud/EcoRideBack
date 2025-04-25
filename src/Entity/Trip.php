<?php

namespace App\Entity;

use App\Repository\TripRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['trip_detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['trip_detail'])]
    private ?string $startingAddress = null;

    #[ORM\Column(length: 255)]
    #[Groups(['trip_detail'])]
    private ?string $arrivalAddress = null;

    #[ORM\Column]
    #[Groups(['trip_detail'])]
    private ?DateTimeImmutable $startingAt = null;

    #[ORM\Column]
    #[Groups(['trip_detail'])]
    private ?int $duration = null;

    #[ORM\Column]
    #[Groups(['trip_detail'])]
    private ?int $nbCredit = null;

    #[ORM\Column]
    #[Groups(['trip_detail'])]
    private ?int $nbPlaceRemaining = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip_detail'])]
    private ?TripStatus $status = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip_detail'])]
    private ?User $owner = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['trip_detail'])]
    private ?Vehicle $vehicle = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'tripsUsers')]
    #[Groups(['trip_detail'])]
    private Collection $User;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $UpdatedAt = null;

    public function __construct()
    {
        $this->User = new ArrayCollection();
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

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getNbCredit(): ?int
    {
        return $this->nbCredit;
    }

    public function setNbCredit(int $nbCredit): static
    {
        $this->nbCredit = $nbCredit;

        return $this;
    }

    public function getNbPlaceRemaining(): ?int
    {
        return $this->nbPlaceRemaining;
    }

    public function setNbPlaceRemaining(int $nbPlaceRemaining): static
    {
        $this->nbPlaceRemaining = $nbPlaceRemaining;

        return $this;
    }

    public function getStatus(): ?TripStatus
    {
        return $this->status;
    }

    public function setStatus(?TripStatus $status): static
    {
        $this->status = $status;

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

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUser(): Collection
    {
        return $this->User;
    }

    public function addUser(User $user): static
    {
        if (!$this->User->contains($user)) {
            $this->User->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->User->removeElement($user);

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
        return $this->UpdatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $UpdatedAt): static
    {
        $this->UpdatedAt = $UpdatedAt;

        return $this;
    }

    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'set')) {
            $property = lcfirst(substr($name, 3));
            if (property_exists($this, $property)) {
                $this->$property = $arguments[0];
                return $this;
            }
        }

        throw new \BadMethodCallException("La méthode {$name} n'existe pas.");
    }
}
