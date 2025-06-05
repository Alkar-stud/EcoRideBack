<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Random\RandomException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ride_control'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user_account', 'ride_control'])]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['user_account'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?string $photo = null;

    #[ORM\Column]
    #[Groups(['user_account'])]
    private ?int $credits = 0;

    #[ORM\Column(nullable: true)]
    #[Groups(['user_account', 'ride_read', 'ride_search'])]
    private ?int $grade = null;

    #[ORM\Column]
    #[Groups(['user_account'])]
    private ?bool $isDriver = false;

    #[ORM\Column]
    #[Groups(['user_account'])]
    private ?bool $isPassenger = true;

    #[ORM\Column(length: 64)]
    #[Groups(['user_account'])]
    private ?string $apiToken;

    #[ORM\Column]
    #[Groups(['ride_control'])]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Groups(['user_account', 'ride_control'])]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user_account', 'ride_control'])]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Preferences>
     */
    #[ORM\OneToMany(targetEntity: Preferences::class, mappedBy: 'user', orphanRemoval: true)]
    #[Groups(['user_account', 'ride_search'])]
    private Collection $userPreferences;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'owner', orphanRemoval: true)]
    #[Groups(['user_account'])]
    private Collection $userVehicles;

    /**
     * @var Collection<int, Ride>
     */
    #[ORM\OneToMany(targetEntity: Ride::class, mappedBy: 'driver', orphanRemoval: true)]
    private Collection $ridesDriver;

    /**
     * @var Collection<int, Ride>
     */
    #[ORM\ManyToMany(targetEntity: Ride::class, mappedBy: 'passenger')]
    private Collection $ridesPassenger;


    /**
     * @throws RandomException
     */
    public function __construct()
    {
        $this->apiToken = bin2hex(random_bytes(32)); // Pour générer un apiToken dès la création d'un utilisateur
        $this->userPreferences = new ArrayCollection();
        $this->userVehicles = new ArrayCollection();
        $this->ridesDriver = new ArrayCollection();
        $this->ridesPassenger = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Ajouter le préfixe ROLE_ et convertir en majuscules
        $roles = array_map(function ($role) {
            return 'ROLE_' . strtoupper($role);
        }, $roles);

        // Garantir que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        // Supprimer ROLE_USER s'il est présent
        $roles = array_filter($roles, function ($role) {
            return $role !== 'ROLE_USER';
        });

        // Retirer le préfixe ROLE_ et convertir en minuscules
        $roles = array_map(function ($role) {
            return strtolower(preg_replace('/^ROLE_/', '', $role));
        }, $roles);

        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCredits(): ?int
    {
        return $this->credits;
    }

    public function setCredits(int $credits): static
    {
        $this->credits = $credits;

        return $this;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function setGrade(?int $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function isDriver(): ?bool
    {
        return $this->isDriver;
    }

    public function setIsDriver(?bool $isDriver): static
    {
        $this->isDriver = $isDriver;

        return $this;
    }

    public function isPassenger(): ?bool
    {
        return $this->isPassenger;
    }

    public function setIsPassenger(?bool $isPassenger): static
    {
        $this->isPassenger = $isPassenger;

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
     * @return Collection<int, Preferences>
     */
    public function getUserPreferences(): Collection
    {
        return $this->userPreferences;
    }

    public function addUserPreference(Preferences $userPreference): static
    {
        if (!$this->userPreferences->contains($userPreference)) {
            $this->userPreferences->add($userPreference);
            $userPreference->setUser($this);
        }

        return $this;
    }

    public function removeUserPreference(Preferences $userPreference): static
    {
        if ($this->userPreferences->removeElement($userPreference)) {
            // set the owning side to null (unless already changed)
            if ($userPreference->getUser() === $this) {
                $userPreference->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getUserVehicles(): Collection
    {
        return $this->userVehicles;
    }

    public function addUserVehicle(Vehicle $userVehicle): static
    {
        if (!$this->userVehicles->contains($userVehicle)) {
            $this->userVehicles->add($userVehicle);
            $userVehicle->setOwner($this);
        }

        return $this;
    }

    public function removeUserVehicle(Vehicle $userVehicle): static
    {
        if ($this->userVehicles->removeElement($userVehicle)) {
            // set the owning side to null (unless already changed)
            if ($userVehicle->getOwner() === $this) {
                $userVehicle->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ride>
     */
    public function getRidesDriver(): Collection
    {
        return $this->ridesDriver;
    }

    public function addRidesDriver(Ride $ridesDriver): static
    {
        if (!$this->ridesDriver->contains($ridesDriver)) {
            $this->ridesDriver->add($ridesDriver);
            $ridesDriver->setDriver($this);
        }

        return $this;
    }

    public function removeRidesDriver(Ride $ridesDriver): static
    {
        if ($this->ridesDriver->removeElement($ridesDriver)) {
            // set the owning side to null (unless already changed)
            if ($ridesDriver->getDriver() === $this) {
                $ridesDriver->setDriver(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Ride>
     */
    public function getRidesPassenger(): Collection
    {
        return $this->ridesPassenger;
    }

    public function addRidesPassenger(Ride $ridesPassenger): static
    {
        if (!$this->ridesPassenger->contains($ridesPassenger)) {
            $this->ridesPassenger->add($ridesPassenger);
            $ridesPassenger->addPassenger($this);
        }

        return $this;
    }

    public function removeRidesPassenger(Ride $ridesPassenger): static
    {
        if ($this->ridesPassenger->removeElement($ridesPassenger)) {
            $ridesPassenger->removePassenger($this);
        }

        return $this;
    }
}
