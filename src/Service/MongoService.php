<?php

namespace App\Service;

use App\Document\MongoEcoRideCreditsTemp;
use App\Document\MongoRideCredit;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use Throwable;


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
    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    function addMovementCreditsForRides($ride, $user, $action, $reason): bool
    {
        $dm = $this->documentManager;

        $mongoRideCredit = new MongoRideCredit();
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
        $mongoRideCredit->setCreatedDate(new DateTimeImmutable());

        $dm->persist($mongoRideCredit);
        $dm->flush();

        //Mise à jour du crédit temporaire de EcoRide
        $this->updateCreditsEcoRide($mongoRideCredit->getAddCredit() - $mongoRideCredit->getWithdrawCredit());

        return true;
    }

    /*
     * Pour ajouter le "log" des mouvements de credits registration, ajout du crédit de bienvenue
     */
    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    function addMovementCreditsForRegistration($valeur, $user, $reason): bool
    {
        $dm = $this->documentManager;

        $mongoRideCredit = new MongoRideCredit();
        $mongoRideCredit->setRideId(0);
        $mongoRideCredit->setUser($user->getId());
        $mongoRideCredit->setAddCredit($valeur);
        $mongoRideCredit->setWithdrawCredit(0);

        $mongoRideCredit->setReason($reason);
        $mongoRideCredit->setCreatedDate(new DateTimeImmutable());

        $dm->persist($mongoRideCredit);
        $dm->flush();

        return true;
    }

    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    function updateCreditsEcoRide($montant): true
    {
        $dm = $this->documentManager;

        $repo = $dm->getRepository(MongoEcoRideCreditsTemp::class);

        $libelle = 'TOTAL_CREDIT_TEMP';
        $creditTemp = $repo->findOneBy(['libelle' => $libelle]);

        if ($creditTemp) {
            $nouvelleValeur = $creditTemp->getValeur() + $montant;
            $creditTemp->setValeur($nouvelleValeur);
            $creditTemp->setUpdatedDate(new DateTimeImmutable());
        } else {
            $creditTemp = new MongoEcoRideCreditsTemp();
            $creditTemp->setLibelle($libelle);
            $creditTemp->setValeur($montant);
            $creditTemp->setUpdatedDate(new DateTimeImmutable());
            $dm->persist($creditTemp);

        }

        $dm->flush();

        return true;
    }

}