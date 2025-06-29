<?php

namespace App\Service;

use App\Document\MongoEcoRideCreditsTemp;
use App\Document\MongoRideCredit;
use App\Document\MongoRideNotice;
use App\Document\MongoValidationHistory;
use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Validation;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use Throwable;

class MongoService
{
    // Constantes pour les actions
    public const ACTION_ADD = 'add';
    public const ACTION_WITHDRAW = 'withdraw';

    // Constantes pour les statuts
    public const STATUS_AWAITING_VALIDATION = 'AWAITINGVALIDATION';

    // Constantes pour les libellés
    public const LABEL_TOTAL_CREDIT_TEMP = 'TOTAL_CREDIT_TEMP';

    public function __construct(
        private readonly DocumentManager $documentManager
    ) {
    }

    /**
     * SECTION: GESTION DES CRÉDITS
     */

    /**
     * Ajoute un mouvement de crédit lié à un trajet
     * @throws Throwable|MongoDBException
     */
    public function addMovementCreditsForRides(Ride $ride, User $user, string $action, string $reason, float $montant): bool
    {
        $mongoRideCredit = new MongoRideCredit();
        $this->setRideCreditProperties($mongoRideCredit, $ride, $user, $action, $reason, $montant);

        $this->documentManager->persist($mongoRideCredit);
        $this->documentManager->flush();

        $this->updateCreditsEcoRide($mongoRideCredit->getAddCredit() - $mongoRideCredit->getWithdrawCredit());

        return true;
    }

    /**
     * Ajoute un mouvement de crédit pour l'inscription
     * @throws MongoDBException|Throwable
     */
    public function addMovementCreditsForRegistration(float $valeur, User $user, string $reason): bool
    {
        $mongoRideCredit = new MongoRideCredit();
        $mongoRideCredit->setRideId(0);
        $mongoRideCredit->setUser($user->getId());
        $mongoRideCredit->setAddCredit($valeur);
        $mongoRideCredit->setWithdrawCredit(0);
        $mongoRideCredit->setReason($reason);
        $mongoRideCredit->setCreatedDate(new DateTimeImmutable());

        $this->documentManager->persist($mongoRideCredit);
        $this->documentManager->flush();

        return true;
    }

    /**
     * Met à jour les crédits EcoRide
     * @throws Throwable|MongoDBException
     */
    public function updateCreditsEcoRide(float $montant): bool
    {
        $repo = $this->documentManager->getRepository(MongoEcoRideCreditsTemp::class);
        $libelle = self::LABEL_TOTAL_CREDIT_TEMP;
        $creditTemp = $repo->findOneBy(['libelle' => $libelle]);

        if ($creditTemp) {
            $creditTemp->setValeur($creditTemp->getValeur() + $montant);
        } else {
            $creditTemp = new MongoEcoRideCreditsTemp();
            $creditTemp->setLibelle($libelle);
            $creditTemp->setValeur($montant);
            $this->documentManager->persist($creditTemp);
        }

        $creditTemp->setUpdatedDate(new DateTimeImmutable());
        $this->documentManager->flush();

        return true;
    }

    /**
     * SECTION : GESTION DES AVIS
     */

    /**
     * Ajoute un avis
     * @throws MongoDBException|Throwable
     */
    public function addNotice(array $notice, User $user, Ride $ride): bool
    {
        $mongoNotice = new MongoRideNotice();

        $mongoNotice->setStatus(self::STATUS_AWAITING_VALIDATION);
        $mongoNotice->setGrade($notice['grade']);
        $mongoNotice->setTitle($notice['title'] ?? null);
        $mongoNotice->setContent($notice['content'] ?? null);
        $mongoNotice->setPublishedBy($user->getId());
        $mongoNotice->setRelatedFor($ride->getId());
        $mongoNotice->setValidateBy(null);
        $mongoNotice->setRefusedBy(null);
        $mongoNotice->setCreatedAt(new DateTimeImmutable());
        $mongoNotice->setUpdatedAt(null);

        $this->documentManager->persist($mongoNotice);
        $this->documentManager->flush();

        return true;
    }

    /**
     * Recherche des avis
     */
    public function searchNotice(User $user, Ride $ride, ?string $status = null): array
    {
        $criteria = [
            'publishedBy' => $user->getId(),
            'relatedFor' => $ride->getId()
        ];

        if ($status !== null) {
            $criteria['status'] = $status;
        }

        return $this->documentManager->getRepository(MongoRideNotice::class)->findBy($criteria);
    }

    /**
     * Met à jour un avis
     * @throws MongoDBException|Throwable
     */
    public function updateNotice(array $notice, User $user, string $action): bool
    {
        $mongoNotice = $this->documentManager->getRepository(MongoRideNotice::class)
            ->findOneBy(['_id' => $notice['id']]);

        if (!$mongoNotice) {
            return false;
        }

        if ($action === 'VALIDATED') {
            $mongoNotice->setStatus('VALIDATED');
            $mongoNotice->setValidateBy($user->getId());
        } else {
            $mongoNotice->setStatus('REFUSED');
            $mongoNotice->setRefusedBy($user->getId());
        }

        $mongoNotice->setUpdatedAt(new DateTimeImmutable());
        $this->documentManager->persist($mongoNotice);
        $this->documentManager->flush();

        return true;
    }

    /**
     * SECTION: GESTION DES VALIDATIONS
     */

    /**
     * Ajoute un historique de validation
     * @throws MongoDBException|Throwable
     */
    public function addValidationHistory(Ride $ride, Validation $validation, User $user, string $content, bool $isClosed = false): bool
    {
        $mongoValidationHistory = new MongoValidationHistory();

        $mongoValidationHistory->setRideId($ride->getId());
        $mongoValidationHistory->setValidationId($validation->getId());
        $mongoValidationHistory->setUser($user->getId());
        $mongoValidationHistory->setAddContent($content);

        if ($isClosed) {
            $mongoValidationHistory->setIsClosed(true);
            $mongoValidationHistory->setClosedBy($user->getId());
        }

        $mongoValidationHistory->setCreatedAt(new DateTimeImmutable());
        $this->documentManager->persist($mongoValidationHistory);
        $this->documentManager->flush();

        return true;
    }

    /**
     * Récupère l'historique de validation
     */
    public function getValidationHistory(array $ride): ?MongoValidationHistory
    {
        return $this->documentManager->getRepository(MongoValidationHistory::class)
            ->findOneBy(['rideId' => $ride['id']]);
    }

    /**
     * Définit les propriétés d'un crédit de trajet
     */
    private function setRideCreditProperties(MongoRideCredit $mongoRideCredit, Ride $ride, User $user, string $action, string $reason, float $montant): void
    {
        $mongoRideCredit->setRideId($ride->getId());
        $mongoRideCredit->setUser($user->getId());

        if ($action === self::ACTION_ADD) {
            $mongoRideCredit->setAddCredit($montant);
            $mongoRideCredit->setWithdrawCredit(0);
        } elseif ($action === self::ACTION_WITHDRAW) {
            $mongoRideCredit->setAddCredit(0);
            $mongoRideCredit->setWithdrawCredit($montant);
        }

        $mongoRideCredit->setReason($reason);
        $mongoRideCredit->setCreatedDate(new DateTimeImmutable());
    }

    public function getNoticesProcessed($isValidated = false): array
    {
        if ($isValidated) {
            // Statut différent de AWAITINGVALIDATION
            return $this->documentManager->getRepository(MongoRideNotice::class)
                ->findBy(['status' => ['$ne' => self::STATUS_AWAITING_VALIDATION]]);
        } else {
            // Statut égal à AWAITINGVALIDATION
            return $this->documentManager->getRepository(MongoRideNotice::class)
                ->findBy(['status' => self::STATUS_AWAITING_VALIDATION]);
        }

    }

    public function findOneNotice(string $id)
    {

        return $this->documentManager->getRepository(MongoRideNotice::class)
            ->findOneBy(['_id' => $id]);
    }

}
