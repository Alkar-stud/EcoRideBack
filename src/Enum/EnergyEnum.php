<?php

namespace App\Enum;

use App\Entity\Vehicle;

enum EnergyEnum: string
{
    case ECO = 'Électrique';
    case ALMOSTECO = 'Hybride';
    case NOECO = 'Carburant inflammable';

    public function findAll(): array
    {
        return [
            new Vehicle(
                EnergyEnum::ECO
            ),
            new Vehicle(
                EnergyEnum::ALMOSTECO
            ),
            new Vehicle(
                EnergyEnum::NOECO
            ),
        ];
    }

}

