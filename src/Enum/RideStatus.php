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
    case BADEXP = 7;

    public function getLabel(): string
    {
        return match($this) {
            self::COMING => 'À Venir',
            self::PROGRESSING => 'En Cours',
            self::VALIDATIONPROCESSING => 'En cours de validation',
            self::CANCELED => 'Annulé',
            self::AWAITINGVALIDATION => 'En Attente De Validation',
            self::FINISHED => 'Terminé',
            self::BADEXP => 'En attente de contrôle'
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

    public static function getBadExpStatus(): string
    {
        foreach (self::cases() as $case) {
            if ($case->value === 7) {
                return $case->name;
            }
        }
        return self::BADEXP->name; // Fallback si jamais la valeur 7 n'est pas trouvée
    }

    public static function getBadExpStatusProcessing(): string
    {
        foreach (self::cases() as $case) {
            if ($case->value === 5) {
                return $case->name;
            }
        }
        return self::AWAITINGVALIDATION->name; // Fallback si jamais la valeur 5 n'est pas trouvée
    }

    public static function getPossibleActions(): array
    {
        return [
            "update" => ["initial" => ["coming"], "become" => "coming"],
            "start" => ["initial" => ["coming"], "become" => "progressing"],
            "stop" => ["initial" => ["progressing"], "become" => "validationprocessing"],
            "badxp" => ["initial" => ["validationprocessing"], "become" => "awaitingValidation"],
            "finish" => ["initial" => ["awaitingValidation", "validationprocessing"], "become" => "finished"],
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
