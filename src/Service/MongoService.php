<?php

namespace App\Service;

use Doctrine\ODM\MongoDB\DocumentManager;


class MongoService
{
    public function __construct(
        public DocumentManager $documentManager
    )
    {
    }

    /*
     * Pour ajouter le "log" des mouvements de credits, user ajoute à son compte, user dépense pour participer à un trajet, user reprend, car annule sa participation
     * user-driver gagne parce que covoiturage est au statut FINISHED, EcoRide retire sa commission quand covoiturage passe au statut FINISHED
     */
    function addMovementCreditsForRides($ride, $user, $action, $reason): bool
    {
        $dm = $this->documentManager;

        $mongoRideCredit = new \App\Document\MongoRideCredit();
        $mongoRideCredit->setRideId($ride->getId());
        $mongoRideCredit->setUser($user->getId());
        if ($action == 'add') {
            $mongoRideCredit->setAddCredit($ride->getPrice());
            $mongoRideCredit->setWithdrawCredit(0);
        } elseif ($action == 'withdraw') {
            $mongoRideCredit->setAddCredit(0);
            $mongoRideCredit->setWithdrawCredit($ride->getPrice());
        } else {
            return false;
        }

        $mongoRideCredit->setReason($reason);
        $mongoRideCredit->setCreatedDate(new \DateTimeImmutable());

        $dm->persist($mongoRideCredit);
        $dm->flush();

        //Mise à jour du crédit temporaire de EcoRide
        $this->updateCreditsEcoRide($mongoRideCredit->getAddCredit() - $mongoRideCredit->getWithdrawCredit());

        return true;
    }

    /*
     * Pour ajouter le "log" des mouvements de credits registration, ajout du crédit de bienvenue
     */
    function addMovementCreditsForRegistration($valeur, $user, $action, $reason): bool
    {
        $dm = $this->documentManager;

        $mongoRideCredit = new \App\Document\MongoRideCredit();
        $mongoRideCredit->setRideId(0);
        $mongoRideCredit->setUser($user->getId());
        $mongoRideCredit->setAddCredit($valeur);
        $mongoRideCredit->setWithdrawCredit(0);

        $mongoRideCredit->setReason($reason);
        $mongoRideCredit->setCreatedDate(new \DateTimeImmutable());

        $dm->persist($mongoRideCredit);
        $dm->flush();

        return true;
    }

    function updateCreditsEcoRide($montant): true
    {
        $dm = $this->documentManager;

        $repo = $dm->getRepository(\App\Document\MongoEcoRideCreditsTemp::class);

        $libelle = 'TOTAL_CREDIT_TEMP';
        $creditTemp = $repo->findOneBy(['libelle' => $libelle]);

        if ($creditTemp) {
            $nouvelleValeur = $creditTemp->getValeur() + $montant;
            $creditTemp->setValeur($nouvelleValeur);
            $creditTemp->setUpdatedDate(new \DateTimeImmutable());
        } else {
            $creditTemp = new \App\Document\MongoEcoRideCreditsTemp();
            $creditTemp->setLibelle($libelle);
            $creditTemp->setValeur($montant);
            $creditTemp->setUpdatedDate(new \DateTimeImmutable());
            $dm->persist($creditTemp);

        }

        $dm->flush();

        return true;
    }

}