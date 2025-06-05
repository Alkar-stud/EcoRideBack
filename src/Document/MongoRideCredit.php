<?php
// src/Document/MongoRideCredit.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "CreditsMovements")]
class MongoRideCredit
{
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "int")]
    private ?int $rideId;

    #[MongoDB\Field(type: "int")]
    private ?int $user;

    #[MongoDB\Field(type: "int")]
    private ?int $addCredit;

    #[MongoDB\Field(type: "int")]
    private ?int $withdrawCredit;

    #[MongoDB\Field(type: "string")]
    private ?string $reason;

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

    public function getUser(): ?int
    {
        return $this->user;
    }

    public function setUser(int $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAddCredit(): ?int
    {
        return $this->addCredit;
    }

    public function setAddCredit(int $addCredit): self
    {
        $this->addCredit = $addCredit;
        return $this;
    }

    public function getWithdrawCredit(): ?int
    {
        return $this->withdrawCredit;
    }

    public function setWithdrawCredit(int $withdrawCredit): self
    {
        $this->withdrawCredit = $withdrawCredit;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

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