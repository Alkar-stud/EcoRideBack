<?php

namespace App\Service;

use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\EcorideRepository;
use App\Repository\RideRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;


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
     * Vérifie si les données sont identiques à celles déjà enregistrées
     * @throws Exception
     */
    public function isRideDataIdentical(Ride $ride, array $dataRequest): bool
    {
        foreach ($dataRequest as $field => $newValue) {
            if ($field === 'vehicle') {
                if ((int)$newValue !== $ride->getVehicle()->getId()) {
                    return false;
                }
            } elseif (in_array($field, ['startingAt', 'arrivalAt'])) {
                $existingDate = $ride->{'get' . ucfirst($field)}();
                $newDate = new DateTimeImmutable($newValue);

                if ($existingDate->format('Y-m-d H:i:s') !== $newDate->format('Y-m-d H:i:s')) {
                    return false;
                }
            } else {
                $getter = 'get' . ucfirst($field);
                if (method_exists($ride, $getter) && $ride->$getter() != $newValue) {
                    return false;
                }
            }
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
     * Valide les données de mise à jour du covoiturage
     */
    public function validateRideUpdateData(
        Ride $ride,
        array &$dataRequest,
        array $champsModifiables,
        int $passengerCount,
        User $user
    ): bool|string {
        // Filtrer les champs non modifiables
        $dataRequest = array_filter(
            $dataRequest,
            fn($key) => in_array($key, $champsModifiables),
            ARRAY_FILTER_USE_KEY
        );

        // Vérification du nombre de places
        if (isset($dataRequest['nbPlacesAvailable'])) {
            if ($dataRequest['nbPlacesAvailable'] <= 0) {
                return "Vous ne pouvez pas mettre 0 place disponible. Annulez le covoiturage.";
            }

            if ($dataRequest['nbPlacesAvailable'] > $ride->getVehicle()->getMaxNbPlacesAvailable()) {
                return "Il n'y a pas assez de place dans la voiture pour accueillir autant de monde.";
            }

            if ($passengerCount > $dataRequest['nbPlacesAvailable']) {
                return "Vous ne pouvez pas mettre moins de places que de participants déjà inscrits";
            }
        }

        // Si des passagers sont inscrits, limiter les champs modifiables
        if ($passengerCount > 0) {
            $restrictedFields = array_diff($champsModifiables, ["vehicle", "nbPlacesAvailable"]);

            foreach ($restrictedFields as $field) {
                if (isset($dataRequest[$field])) {
                    return "Vous ne pouvez pas modifier cela lorsqu'il y a des participants";
                }
            }
        }

        // Vérification du véhicule
        if (isset($dataRequest['vehicle'])) {
            $vehicleId = $dataRequest['vehicle'];
            $vehicle = $this->manager->getRepository(Vehicle::class)->findOneBy([
                'id' => $vehicleId,
                'owner' => $user->getId()
            ]);

            if (!$vehicle) {
                return "Ce véhicule n'existe pas.";
            }

            // Vérifier si le véhicule a assez de places
            if ($passengerCount > $vehicle->getMaxNbPlacesAvailable()) {
                return "Ce véhicule n'a pas assez de places pour les passagers déjà inscrits.";
            }

            // Stocker l'objet véhicule pour l'utiliser plus tard
            $dataRequest['vehicleObject'] = $vehicle;
        }

        return true;
    }

    /**
     * Met à jour l'entité Ride avec les nouvelles données
     * @throws Exception
     */
    public function updateRideEntity(Ride $ride, array $dataRequest): void
    {
        foreach ($dataRequest as $field => $value) {
            if ($field === 'vehicle') {
                $ride->setVehicle($dataRequest['vehicleObject']);
            } elseif (in_array($field, ['startingAt', 'arrivalAt'])) {
                $setter = 'set' . ucfirst($field);
                $ride->$setter(new DateTimeImmutable($value));
            } elseif ($field !== 'vehicleObject') { // Ignorer notre champ temporaire
                $setter = 'set' . ucfirst($field);
                if (method_exists($ride, $setter)) {
                    $ride->$setter($value);
                }
            }
        }

        $ride->setUpdatedAt(new DateTimeImmutable());

        $this->manager->persist($ride);
        $this->manager->flush();
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
                'nbPlacesAvailable' => $ride->getNbPlacesAvailable(),
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
    }



}
