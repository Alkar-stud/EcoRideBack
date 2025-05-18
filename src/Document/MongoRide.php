<?php
// src/Document/MongoRide.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "rides")]
class MongoRide
{
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "int")]
    private $rideId;

    #[MongoDB\Field(type: "hash")]
    private $startingAddress;

    #[MongoDB\Field(type: "hash")]
    private $arrivalAddress;

    #[MongoDB\Field(type: "date")]
    private $startingAt;

    #[MongoDB\Field(type: "date")]
    private $arrivalAt;

    #[MongoDB\Field(type: "int")]
    private $duration;

    #[MongoDB\Field(type: "int")]
    private $price;

    #[MongoDB\Field(type: "int")]
    private $nbPlacesAvailable;

    #[MongoDB\Field(type: "int")]
    private $nbParticipant = 0;

    #[MongoDB\Field(type: "hash")]
    private $driver;

    #[MongoDB\Field(type: "hash")]
    private $vehicle;

    #[MongoDB\Field(type: "date")]
    private $createdDate;

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

    public function getStartingAddress(): ?array
    {
        return $this->startingAddress;
    }

    public function setStartingAddress(array $startingAddress): self
    {
        $this->startingAddress = $startingAddress;
        return $this;
    }

    public function getArrivalAddress(): ?array
    {
        return $this->arrivalAddress;
    }

    public function setArrivalAddress(array $arrivalAddress): self
    {
        $this->arrivalAddress = $arrivalAddress;
        return $this;
    }

    public function getStartingAt(): ?string
    {
        return $this->startingAt;
    }

    public function setStartingAt($startingAt): self
    {
        $this->startingAt = $startingAt;
        return $this;
    }

    public function getArrivalAt(): ?string
    {
        return $this->arrivalAt;
    }

    public function setArrivalAt( $arrivalAt): self
    {
        $this->arrivalAt = $arrivalAt;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getNbPlacesAvailable(): ?int
    {
        return $this->nbPlacesAvailable;
    }

    public function setNbPlacesAvailable(int $nbPlacesAvailable): self
    {
        $this->nbPlacesAvailable = $nbPlacesAvailable;
        return $this;
    }

    public function getNbParticipant(): ?int
    {
        return $this->nbParticipant;
    }

    public function setNbParticipant(int $nbParticipant): self
    {
        $this->nbParticipant = $nbParticipant;
        return $this;
    }

    public function getDriver(): ?array
    {
        return $this->driver;
    }

    public function setDriver(array $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function getVehicle(): ?array
    {
        return $this->vehicle;
    }

    public function setVehicle(array $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getCreatedDate(): ?string
    {
        return $this->createdDate;
    }

    public function setCreatedDate( $createdDate): self
    {
        $this->createdDate = $createdDate;
        return $this;
    }
}
