<?php
// src/Service/RidePayments.php
namespace App\Service;


use App\Enum\RideStatus;
use App\Repository\EcorideRepository;
use Doctrine\ODM\MongoDB\MongoDBException;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

readonly class RidePayments
{
    public function __construct(
        private EntityManagerInterface $manager,
        private EcoRideRepository      $repositoryEcoRide,
        private MongoService           $mongoService,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    public function driverPayment($ride,$nbPassengers): true
    {
        //Statut de fin par défaut
        $ride->setStatus(RideStatus::getFinishedStatus());
        //Appel de la fonction ecoRidePayment pour payer la plateforme
        $platformCommission = $this->ecoRidePayment($ride);

        $payment = ($nbPassengers * $ride->getPrice());
        //On met à jour le crédit du chauffeur
        $ride->getDriver()->setCredits($ride->getDriver()->getCredits() + $payment - $platformCommission->getParameterValue());
        $this->manager->persist($ride);
        $this->manager->flush();

        $this->mongoService->addMovementCreditsForRides($ride, $ride->getDriver(), 'withdraw', 'Paiement du chauffeur pour le covoiturage ' . $ride->getId(), $payment - $platformCommission->getParameterValue());

        return true;
    }

    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    protected function ecoRidePayment($ride)
    {
        //Récupération de la commission, parameterValue de l'entité EcoRide dont le libelle est PLATFORM_COMMISSION_CREDIT.
        $platformCommission = $this->repositoryEcoRide->findOneBy(['libelle' => 'PLATFORM_COMMISSION_CREDIT']);
        if (!$platformCommission) {
            $platformCommission->setParameterValue(0);
        }
        //On récupère le crédit total de EcoRide
        $platformTotalCredits = $this->repositoryEcoRide->findOneBy(['libelle' => 'TOTAL_CREDIT']);
        //On ajoute la commission au crédit total de EcoRide
        $platformTotalCredits->setParameterValue($platformCommission->getParameterValue() + $platformTotalCredits->getParameterValue());
        $this->manager->persist($platformTotalCredits);
        $this->manager->flush();

        $this->mongoService->addMovementCreditsForRides($ride, $ride->getDriver(), 'withdraw', 'Commission de la plateforme pour le covoiturage ' . $ride->getId(), $platformCommission->getParameterValue());

        return $platformCommission;

    }

}