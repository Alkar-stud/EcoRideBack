<?php
// src/Enum/RideStatus.php

namespace App\Enum;

enum RideStatus: int
{
    case COMING = 1;
    case PROGRESSING = 2;
    case VALIDATIONPROCESSING = 3;
    case FINISHED = 4;
    case CANCELED = 5;
    case AWAITINGVALIDATION = 6;

    public function getLabel(): string
    {
        return match($this) {
            self::COMING => 'À Venir',
            self::PROGRESSING => 'En Cours',
            self::VALIDATIONPROCESSING => 'Approuvé',
            self::FINISHED => 'Terminé',
            self::CANCELED => 'Annulé',
            self::AWAITINGVALIDATION => 'En Attente De Validation'
        };
    }
}
