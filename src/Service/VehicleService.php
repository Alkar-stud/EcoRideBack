<?php

namespace App\Service;

use App\Entity\Vehicle;
use App\Enum\EnergyEnum;

class VehicleService
{
    /**
     * Convertit le code d'énergie en valeur descriptive pour l'affichage
     */
    public function convertEnergyCodeToValue(Vehicle $vehicle): void
    {
        $energyMapping = [];
        foreach (EnergyEnum::cases() as $case) {
            $energyMapping[$case->name] = $case->value;
        }

        $energyCode = $vehicle->getEnergy();
        if (isset($energyMapping[$energyCode])) {
            $vehicle->setEnergy($energyMapping[$energyCode]);
        }
    }
}