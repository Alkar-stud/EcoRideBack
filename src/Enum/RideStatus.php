<?php
// src/Enum/RideStatus.php

namespace App\Enum;

enum RideStatus: int
{
    case COMING = 1;
    case PROGRESSING = 2;
    case VALIDATIONPROCESSING = 3;
    case CANCELED = 4;
    case AWAITINGVALIDATION = 5;
    case FINISHED = 6;

    public function getLabel(): string
    {
        return match($this) {
            self::COMING => 'À Venir',
            self::PROGRESSING => 'En Cours',
            self::VALIDATIONPROCESSING => 'Approuvé',
            self::CANCELED => 'Annulé',
            self::AWAITINGVALIDATION => 'En Attente De Validation',
            self::FINISHED => 'Terminé'
        };
    }

    public static function getDefaultStatus(): string
    {
        foreach (self::cases() as $case) {
            if ($case->value === 1) {
                return $case->name;
            }
        }
        return self::COMING->name; // Fallback si jamais la valeur 1 n'est pas trouvée
    }

    public static function getFinishedStatus(): string
    {
        foreach (self::cases() as $case) {
            if ($case->value === 6) {
                return $case->name;
            }
        }
        return self::FINISHED->name; // Fallback si jamais la valeur 6 n'est pas trouvée
    }

    public static function getPossibleActions(): array
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

    public static function getRideFieldsUpdatable(): array
    {
        return [
            "vehicle", "price", "nbPlacesAvailable"
        ];
    }

}
