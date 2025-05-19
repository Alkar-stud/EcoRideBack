<?php

namespace App\Service;

use App\Document\MongoRide;
use DateTime;
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
        $ride->setStartingAddress($data['startingAddress']);  //Doit être un array ['street', 'postcode', 'city']
        $ride->setArrivalAddress($data['arrivalAddress']); //Doit être un array ['street', 'postcode', 'city']
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

    public function findBySearch(array $dataRequest): ?array
    {
        //$queryBuilder = $this->documentManager->createQueryBuilder(MongoRide::class);

dd($dataRequest);




        if (!$rides || empty($rides)) {
            return null;
        }

        // Convertir la collection d'objets en tableau
        $result = [];


        return $result;
    }


    public function findById(int $id): ?array
    {
        $ride = $this->documentManager->getRepository(MongoRide::class)->findOneBy(['rideId' => $id]);

        if (!$ride) {
            return null;
        }

        // Convertir l'objet en tableau avec les dates correctement formatées
        return [
            'rideId' => $ride->getRideId(),
            'startingAddress' => $ride->getStartingAddress(),
            'arrivalAddress' => $ride->getArrivalAddress(),
            'startingAt' => $ride->getStartingAt() instanceof DateTime ? $ride->getStartingAt()->format('Y-m-d H:i:s') : $ride->getStartingAt(),
            'arrivalAt' => $ride->getArrivalAt() instanceof DateTime ? $ride->getArrivalAt()->format('Y-m-d H:i:s') : $ride->getArrivalAt(),
            'duration' => $ride->getDuration(),
            'price' => $ride->getPrice(),
            'nbPlacesAvailable' => $ride->getNbPlacesAvailable(),
            'driver' => $ride->getDriver(),
            'vehicle' => $ride->getVehicle(),
            'createdDate' => $ride->getCreatedDate() instanceof DateTime ? $ride->getCreatedDate()->format('Y-m-d H:i:s') : $ride->getCreatedDate(),
            'updatedDate' => $ride->getUpdatedDate() instanceof DateTime ? $ride->getUpdatedDate()->format('Y-m-d H:i:s') : $ride->getUpdatedDate(),
        ];
    }


}
