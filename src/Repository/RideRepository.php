<?php

namespace App\Repository;

use App\Entity\Ride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ride>
 */
class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

//    /**
//     * @return Ride[] Returns an array of Ride objects
//     */
    public function findBySomeField($criteria): array
    {
        //La date ne va pas dans la requête, car on doit être capable de proposer après la date la plus proche.
        $qb = $this->createQueryBuilder('r')
            ->where('r.startingCity = :startingCity')
            ->setParameter('startingCity', $criteria['startingCity'])
            ->andWhere('r.arrivalCity = :arrivalCity')
            ->setParameter('arrivalCity', $criteria['arrivalCity'])
            ->andWhere('r.arrivalCity = :arrivalCity')
            ->setParameter('arrivalCity', $criteria['arrivalCity'])
            ->leftJoin('r.passenger', 'p')
            ->groupBy('r.id')
            ->having('r.nbPlacesAvailable > COUNT(p.id)');

        // Filtrer les trajets qui ont encore des places disponibles
        $qb->groupBy('r.id')                // Regroupement par l'ID du trajet
        ->having('r.nbPlacesAvailable > COUNT(p.id)'); // Vérification que le nombre de places disponibles est supérieur au nombre de passagers

        // Filtres optionnels
        if (isset($criteria['maxPrice'])) {
            $qb->andWhere('r.price <= :maxPrice')
                ->setParameter('maxPrice', $criteria['maxPrice']);
        }

        if (isset($criteria['maxDuration'])) {
            // La durée est fournie en minutes, mais nous devons comparer avec
            // la différence de temps entre arrivalAt et startingAt
            $qb->andWhere('TIMESTAMPDIFF(MINUTE, r.startingAt, r.arrivalAt) <= :maxDuration')
                ->setParameter('maxDuration', $criteria['maxDuration']);
        }

        if (isset($criteria['MinDriverGrade'])) {
            $qb->leftJoin('r.driver', 'u')
                ->andWhere('u.grade >= :MinDriverGrade')
                ->setParameter('MinDriverGrade', $criteria['MinDriverGrade']);
        }

        if (isset($criteria['isEco']) && $criteria['isEco'] === true) {
            $qb->leftJoin('r.vehicle', 'v')
                ->andWhere('v.energy = :ecoEnergy')
                ->setParameter('ecoEnergy', 'ECO');
        }


        // Filtre sur l'état du covoiturage (ne pas montrer les annulés, etc.)
        $qb->andWhere('r.status = :status')
            ->setParameter('status', 'COMING');


        $results = $qb->orderBy('r.startingAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Ajouter le nombre de places restantes
        $ridesWithRemainingSeats = [];
        foreach ($results as $ride) {
            $remainingSeats = $ride->getNbPlacesAvailable() - $ride->getPassenger()->count();
            $rideArray = [
                'ride' => $ride,
                'remainingSeats' => $remainingSeats
            ];
            $ridesWithRemainingSeats[] = $rideArray;
        }

        return $ridesWithRemainingSeats;
    }

}
