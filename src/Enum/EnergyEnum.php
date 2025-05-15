<?php

namespace App\Enum;

use App\Entity\Vehicle;

enum EnergyEnum: string
{
    case ECO = 'Électrique';
    case ALMOSTECO = 'Hybride';
    case NOTECO = 'Carburant inflammable';

    // Définir une constante qui contient tous les noms
    public const NAMES = ['ECO', 'ALMOSTECO', 'NOTECO'];

    /**
     * Retourne tous les noms des cas de l'énumération
     */
    public static function getNames(): array
    {
        return array_map(fn($case) => $case->name, self::cases());
    }
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
                EnergyEnum::NOTECO
            ),
        ];
    }

}

