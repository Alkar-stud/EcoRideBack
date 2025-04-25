<?php

namespace App\Service;

use App\Entity\TripStatus;
use App\Entity\Vehicle;
use App\Repository\EcoRideRepository;
use App\Repository\TripStatusRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;


class TripService
{
    private EcoRideRepository $ecoRideRepository;
    private VehicleRepository $vehicleRepository;
    private TripStatusRepository $tripStatusRepository;

    public function __construct(
        private readonly EntityManagerInterface  $manager,
        EcoRideRepository $ecoRideRepository,
        VehicleRepository $vehicleRepository,
        TripStatusRepository $tripStatusRepository
    )
    {
        $this->ecoRideRepository = $ecoRideRepository;
        $this->vehicleRepository = $vehicleRepository;
        $this->tripStatusRepository = $tripStatusRepository;

    }

    public function getDefaultStatus():  ?TripStatus
    {
        $ecoRide = $this->ecoRideRepository->findOneBy(['libelle' => 'DEFAULT_TRIP_STATUS_ID']);

        if (!$ecoRide) {
            return null;
        }

        $statusId = $ecoRide->getParameters();
        $statusRepository = $this->manager->getRepository(TripStatus::class);

        return $statusRepository->find($statusId);
    }

    public function getFinishedStatus():  ?TripStatus
    {
        $ecoRide = $this->ecoRideRepository->findOneBy(['libelle' => 'FINISHED_TRIP_STATUS_ID']);

        if (!$ecoRide) {
            return null;
        }

        $statusId = $ecoRide->getParameters();
        $statusRepository = $this->manager->getRepository(TripStatus::class);

        return $statusRepository->find($statusId);
    }

    public function getTripVehicle($vehicleId, $user): ?Vehicle
    {
        $vehicle = $this->vehicleRepository->findOneBy(['id' => $vehicleId]);

        if (!$vehicle) {
            return null;
        }
        //On vérifie si le véhicule appartient bien à CurrentUser
        if ($vehicle->getOwner()->getId() !== $user->getId()) {
            return null;
        }

        return $vehicle;

    }

    public function getPossibleStatus(): array
    {
        $statuses = $this->tripStatusRepository->findAll();
        $result['all'] = 'all';
        foreach (($statuses) as $status) {
            $result[$status->getCode()] = $status->getId();
        }
        return $result;
    }

    public function getPossibleActions(): array
    {
        return [
            "update" => ["initial" => ["coming"], "become" => "coming"],
            "start" => ["initial" => ["coming"], "become" => "progressing"],
            "stop" => ["initial" => ["progressing"], "become" => "validationProcess"],
            "badxp" => ["initial" => ["validationProcess"], "become" => "awaitingValidation"],
            "finish" => ["initial" => ["awaitingValidation", "validationProcess"], "become" => "finished"],
            "cancel" => ["initial" => ["coming"], "become" => "canceled"]
        ];
    }


}