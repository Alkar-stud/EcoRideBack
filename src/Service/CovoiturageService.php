<?php

namespace App\Service;

use App\Repository\EcoRideRepository;

class CovoiturageService
{
    private EcoRideRepository $ecoRideRepository;

    public function __construct(EcoRideRepository $ecoRideRepository)
    {
        $this->ecoRideRepository = $ecoRideRepository;
    }

    public function getDefaultStatus(): ?string
    {
        $ecoRide = $this->ecoRideRepository->findOneBy(['libelle' => 'DEFAULT_COVOITURAGE_STATUS']);
        // Vérification si l'entité existe et récupération de la valeur des paramètres
        return $ecoRide ? $ecoRide->getParameters() : null;
    }
}