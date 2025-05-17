<?php

namespace App\Service;

use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\EcorideRepository;
use App\Repository\RideRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\MongoDBException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;


class RideService
{
    private EcorideRepository $ecorideRepository;
    private RideRepository $rideRepository;
    private MongoService $mongoService;
    private MailService $mailService;

    public function __construct(
        private readonly EntityManagerInterface $manager,
        EcorideRepository                       $ecorideRepository,
        RideRepository                          $rideRepository,
        MongoService                            $mongoService,
        MailService                             $mailService,
    )
    {
        $this->ecorideRepository = $ecorideRepository;
        $this->rideRepository = $rideRepository;
        $this->mongoService = $mongoService;
        $this->mailService = $mailService;
    }

    public function getPossibleStatus(): array
    {
        $statuses = $this->rideRepository->findAll();
        $result['all'] = 'all';
        foreach (($statuses) as $status) {
            $result[$status->getCode()] = $status->getId();
        }
        return $result;
    }

    //Valide si l'action existe et est possible.
    public function validateEditRequest($action, array $possibleActions): bool
    {
        if (!isset($action) || !array_key_exists($action, $possibleActions)) {
            return false;
        }
        return true;
    }


    /**
     * @throws Exception
     */
    public function validateConsistentData(array $tabData, User $user): array
    {
        //startingAddress et arrivalAddress non vide et non identiques
        if ((empty($tabData['startingAddress']) || empty($tabData['arrivalAddress'])) )
         {
            return ['error' => 'AddressMissing'];
        }
        if ($tabData['startingAddress'] === $tabData['arrivalAddress']) {
            return ['error' => 'AddressSame'];
        }

        //Vérification sur les dates
        if (empty($tabData['startingAt']) || empty($tabData['arrivalAt'])) {
            return ['error' => 'DateMissing'];
        }
        // Vérifier que les dates sont bien des dates et cohérentes l'une par rapport à l'autre
        try {
            $startingAt = new DateTimeImmutable($tabData['startingAt']);
            $arrivalAt = new DateTimeImmutable($tabData['arrivalAt']);
        } catch (Exception) {
            return ['error' => 'InvalidDateFormat'];
        }

        if ($startingAt >= $arrivalAt) {
            return ['error' => 'DatesHoursInconsistent'];
        }

        //price est bien supérieur à PLATFORM_COMMISSION_CREDIT
        if (empty($tabData['price'])) {
            return ['error' => 'PriceMissing'];
        }
        $price = $tabData['price'];
        $platformCommission = $this->ecorideRepository->findOneBy(['libelle' => 'PLATFORM_COMMISSION_CREDIT']);
        if ($price < $platformCommission->getParameterValue()) {
            return ['error' => 'PriceTooLowMin_' . $platformCommission->getParameterValue()];
        }

        //Si nbPlacesAvailable est bien > à 0.
        if ($tabData['nbPlacesAvailable'] <= 0) {
            return ['error' => 'NotEnoughPlacesAvailable'];
        }

        //Le véhicule existe et appartient au user
        if (empty($tabData['vehicle'])) {
            return ['error' => 'VehicleMissing'];
        }
        $vehicleId = $tabData['vehicle'];
        $vehicle = $this->manager->getRepository(Vehicle::class)->findOneBy(['id' => $vehicleId, 'owner' => $user->getId()]);
        if (!$vehicle) {
            return ['error' => 'VehicleNotFound'];
        }
        //Vérification que le véhicule a assez de place max
        if ($vehicle->getMaxNbPlacesAvailable() < $tabData['nbPlacesAvailable']) {
            return ['error' => 'VehicleNotEnoughPlaces'];
        }

        return ['message' => 'ok'];
    }


    /**
     * Notifie les passagers des modifications
     * @throws TransportExceptionInterface
     */
    public function notifyPassengersAboutRideUpdate(Ride $ride, Collection $passengers): void
    {
        foreach ($passengers as $passenger) {
            $this->mailService->sendEmail(
                $passenger->getEmail(),
                'vehicleIsChanged',
                ['date' => $ride->getStartingAt()->format('d/m/Y H:i')]
            );
        }
    }

    /**
     * Met à jour les données dans MongoDB
     */
    public function updateRideInMongo(Ride $ride): void
    {

        try {
            $this->mongoService->update(
                ['rideId' => $ride->getId()],
                [
                    'startingAddress' => $ride->getStartingAddress(),
                    'arrivalAddress' => $ride->getArrivalAddress(),
                    'startingAt' => $ride->getStartingAt(),
                    'arrivalAt' => $ride->getArrivalAt(),
                    'duration' => ($ride->getArrivalAt()->diff($ride->getStartingAt())->h * 60) +
                        $ride->getArrivalAt()->diff($ride->getStartingAt())->i,
                    'price' => $ride->getPrice(),
                    'nbPlacesAvailable' => $ride->getNbPlacesAvailable() - count($ride->getPassenger()),
                    'nbParticipant' => count($ride->getPassenger()),
                    'updatedAt' => $ride->getUpdatedAt(),
                    'vehicle' => [
                        'brand' => $ride->getVehicle()->getBrand(),
                        'model' => $ride->getVehicle()->getModel(),
                        'color' => $ride->getVehicle()->getColor(),
                        'energy' => $ride->getVehicle()->getEnergy(),
                        'isEco' => $ride->getVehicle()->getEnergy() === 'ECO',
                    ],
                ]
            );
        } catch (MongoDBException|Throwable $e) {

        }
    }




}
