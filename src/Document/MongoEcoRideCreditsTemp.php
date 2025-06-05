<?php
// src/Document/MongoRideCreditTemp.php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document(collection: "ecoRideCreditsTemp")]
class MongoEcoRideCreditsTemp
{
    #[MongoDB\Id]
    protected $id;

    #[MongoDB\Field(type: "int")]
    private ?int $creditTemp;

    #[MongoDB\Field(type: "string")]
    private ?string $libelle;

    #[MongoDB\Field(type: "int")]
    private ?int $valeur;

    #[MongoDB\Field(type: "date")]
    private $updatedDate;

    public function getId()
    {
        return $this->id;
    }

    public function getCreditTemp(): ?int
    {
        return $this->creditTemp;
    }

    public function setCreditTemp(int $creditTemp): self
    {
        $this->creditTemp = $creditTemp;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getValeur(): ?int
    {
        return $this->valeur;
    }

    public function setValeur(int $valeur): self
    {
        $this->valeur = $valeur;
        return $this;
    }
    public function getUpdatedDate(): ?string
    {
        return $this->updatedDate;
    }

    public function setUpdatedDate( $updatedDate): self
    {
        $this->updatedDate = $updatedDate;
        return $this;
    }


}