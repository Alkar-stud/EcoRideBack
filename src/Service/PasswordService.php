<?php
// src/Service/PasswordService.php
namespace App\Service;

readonly class PasswordService
{
    public function passwordGeneration($nbChar): string
    {
        //Validation de la complexité du mot de passe
        $passGen = '';
        while (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $passGen))
        {
            $passGen = substr(str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCEFGHIJKLMNOPQRSTUVWXYZ0123456789.*!?'),1, $nbChar);
        }
        return $passGen;
    }

}