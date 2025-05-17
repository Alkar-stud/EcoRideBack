<?php

namespace App\Service;

use App\Document\MongoRide;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use Exception;
use Throwable;

class MongoService
{
    public function __construct(
        public DocumentManager $documentManager
    )
    {
    }


    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    public function add(array $data): void
    {
        // Ne pas convertir les dates en format ISO 8601 avant d'hydrater l'objet MongoRide
        // car ses setters attendent des objets DateTimeInterface.

        // Création et hydratation de l'objet MongoRide
        $ride = new MongoRide();
        $ride->setRideId($data['rideId']);
        $ride->setStartingAddress($data['startingAddress']);
        $ride->setArrivalAddress($data['arrivalAddress']);
        $ride->setStartingAt($data['startingAt']); // Doit être un DateTimeInterface
        $ride->setArrivalAt($data['arrivalAt']); // Doit être un DateTimeInterface
        $ride->setDuration($data['duration']);
        $ride->setPrice($data['price']);
        $ride->setNbPlacesAvailable($data['nbPlacesAvailable']);
        $ride->setDriver($data['driver']);
        $ride->setVehicle($data['vehicle']);

        $this->documentManager->persist($ride);
        $this->documentManager->flush();
    }


    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    public function update(array $criteria, array $data): void
{
    // Rechercher le document existant
    $ride = $this->documentManager->getRepository(MongoRide::class)->findOneBy($criteria);

    if (!$ride) {
        return;
    }

    // Mise à jour des champs
    if (isset($data['startingAddress'])) {
        $ride->setStartingAddress($data['startingAddress']);
    }
    if (isset($data['arrivalAddress'])) {
        $ride->setArrivalAddress($data['arrivalAddress']);
    }
    if (isset($data['startingAt'])) {
        $ride->setStartingAt($data['startingAt']);
    }
    if (isset($data['arrivalAt'])) {
        $ride->setArrivalAt($data['arrivalAt']);
    }
    if (isset($data['duration'])) {
        $ride->setDuration($data['duration']);
    }
    if (isset($data['price'])) {
        $ride->setPrice($data['price']);
    }
    if (isset($data['nbPlacesAvailable'])) {
        $ride->setNbPlacesAvailable($data['nbPlacesAvailable']);
    }
    if (isset($data['nbParticipant'])) {
        $ride->setNbParticipant($data['nbParticipant']);
    }
    if (isset($data['vehicle'])) {
        $ride->setVehicle($data['vehicle']);
    }



    // Persistance des modifications
    $this->documentManager->persist($ride);
    $this->documentManager->flush();
}


    public function delete(int $id): bool
    {
        try {
            $ride = $this->documentManager->getRepository(MongoRide::class)->findOneBy(['rideId' => $id]);

            if (!$ride) {
                return false;
            }

            $this->documentManager->remove($ride);
            $this->documentManager->flush();

            return true;
        } catch (Exception $e) {
            error_log("mongodb_delete_exception: Erreur lors de la suppression de l'id $id - " . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            error_log("mongodb_delete_throwable: Erreur lors de la suppression de l'id $id - " . $e->getMessage());
        }
        return true;
    }

    public function findById(int $id): ?array
    {
        $ride = $this->documentManager->getRepository(MongoRide::class)->findOneBy(['rideId' => $id]);

        if (!$ride) {
            return null;
        }

        // Convertir l'objet en tableau
        return [
            'rideId' => $ride->getRideId(),
            'startingAddress' => $ride->getStartingAddress(),
            'arrivalAddress' => $ride->getArrivalAddress(),
            'startingAt' => $ride->getStartingAt(),
            'arrivalAt' => $ride->getArrivalAt(),
            'duration' => $ride->getDuration(),
            'price' => $ride->getPrice(),
            'nbPlacesAvailable' => $ride->getNbPlacesAvailable(),
            'driver' => $ride->getDriver(),
            'vehicle' => $ride->getVehicle(),
            'createdDate' => $ride->getCreatedDate(),
            'updatedDate' => $ride->getUpdatedDate(),
        ];
    }


}
