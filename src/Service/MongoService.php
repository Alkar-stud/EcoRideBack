<?php

namespace App\Service;

use App\Document\MongoEcoRideCreditsTemp;
use App\Document\MongoRideCredit;
use App\Document\MongoRideNotice;
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
    function addMovementCreditsForRides($ride, $user, $action, $reason, $montant): bool
    {
        $dm = $this->documentManager;

        $mongoRideCredit = new MongoRideCredit();
        $mongoRideCredit->setRideId($ride->getId());
        $mongoRideCredit->setUser($user->getId());
        if ($action == 'add') {
            $mongoRideCredit->setAddCredit($montant);
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

    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    function addNotice($notice, $user, $ride): true
    {
        $dm = $this->documentManager;

        $mongoNotice = new MongoRideNotice();
        $mongoNotice->setStatus('AWAITINGVALIDATION');
        $mongoNotice->setGrade($notice['grade']);
        $mongoNotice->setTitle($notice['title'] ?? null);
        $mongoNotice->setContent($notice['content'] ?? null);
        $mongoNotice->setPublishedBy($user->getId());
        $mongoNotice->setRelatedFor($ride->getId());
        $mongoNotice->setValidateBy(null);
        $mongoNotice->setRefusedBy(null);
        $mongoNotice->setCreatedAt(new DateTimeImmutable());
        $mongoNotice->setUpdatedAt(null);

        $dm->persist($mongoNotice);
        $dm->flush();

        return true;
    }

    function searchNotice($user, $ride, $status = null): array
    {
        $dm = $this->documentManager;
        $repo = $dm->getRepository(MongoRideNotice::class);

        //Filtre en fonction du statut
        $criteria = [
            'publishedBy' => $user->getId(),
            'relatedFor' => $ride->getId()
        ];

        if ($status !== null) {
            $criteria['status'] = $status;
        }

        return $repo->findBy($criteria);
    }


    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    function updateNotice($notice, $user, $action)
    {
        $dm = $this->documentManager;

        //Récupération de la notice
        $mongoNotice = $dm->getRepository(MongoRideNotice::class)->findOneBy(['_id' => $notice['id']]);

        if (!$mongoNotice)
        {
            return false;
        }

        if ($action == 'validated')
        {
            $mongoNotice->setRefusedBy($user);
        } else {
            $mongoNotice->setValidateBy($user);
        }

        $mongoNotice->setUpdatedAt(new DateTimeImmutable());

        $dm->persist($mongoNotice);
        $dm->flush();

        return true;
    }


}