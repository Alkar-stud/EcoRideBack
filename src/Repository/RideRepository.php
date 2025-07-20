<?php

namespace App\Repository;

use App\Entity\Ride;
use App\Enum\RideStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    /**
     * Recherche des trajets en fonction des critères donnés
     * @param array $criteria Les critères de recherche
     * @return array Les trajets correspondants avec le nombre de places restantes
     */
    public function findBySomeField(array $criteria): array
    {
        $qb = $this->createBaseQueryBuilder($criteria);

        // Correction du filtre isEco
        if (isset($criteria['isEco'])) {
            $isEco = filter_var($criteria['isEco'], FILTER_VALIDATE_BOOLEAN);
            if ($isEco) {
                $qb->leftJoin('r.vehicle', 'v')
                    ->andWhere('v.energy = :ecoEnergy')
                    ->setParameter('ecoEnergy', 'ECO');
            } else {
                $qb->leftJoin('r.vehicle', 'v')
                    ->andWhere('v.energy != :ecoEnergy')
                    ->setParameter('ecoEnergy', 'ECO');
            }
        }

        // Ajout d'une condition pour filtrer par date minimale
        if (isset($criteria['startingAt'])) {
            $qb->andWhere('r.startingAt >= :minDate')
                ->setParameter('minDate', $criteria['startingAt'] . ' 00:00:00');;
            $qb->andWhere('r.startingAt <= :maxDate')
                ->setParameter('maxDate', $criteria['startingAt'] . ' 23:59:59');

        }

        $results = $qb->orderBy('r.startingAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->addRemainingSeatsInfo($results);
    }

    /**
     * Trouve le trajet le plus proche avant la date donnée
     * @param array $criteria Les critères de recherche
     * @param DateTimeImmutable $searchDate La date de recherche
     * @param DateTimeImmutable $minDate La date minimale (aujourd'hui)
     * @return array|null Le trajet le plus proche ou null
     */
    public function findOneClosestBefore(array $criteria, DateTimeImmutable $searchDate, DateTimeImmutable $minDate): ?array
    {
        $qb = $this->createBaseQueryBuilder($criteria)
            ->andWhere('r.startingAt < :searchDate')
            ->setParameter('searchDate', $searchDate)
            ->andWhere('r.startingAt >= :minDate')
            ->setParameter('minDate', $minDate)
            ->orderBy('r.startingAt', 'DESC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getResult();

        return !empty($result) ? $this->addRemainingSeatsInfo([$result[0]])[0] : null;
    }

    /**
     * Trouve le trajet le plus proche après la date donnée
     * @param array $criteria Les critères de recherche
     * @param DateTimeImmutable $searchDate La date de recherche
     * @return array|null Le trajet le plus proche ou null
     */
    public function findOneClosestAfter(array $criteria, DateTimeImmutable $searchDate): ?array
    {
        $qb = $this->createBaseQueryBuilder($criteria)
            ->andWhere('r.startingAt > :searchDate')
            ->setParameter('searchDate', $searchDate)
            ->orderBy('r.startingAt', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getResult();

        return !empty($result) ? $this->addRemainingSeatsInfo([$result[0]])[0] : null;
    }

    /**
     * Crée un QueryBuilder de base avec les critères communs
     * @param array $criteria Les critères de recherche
     * @return QueryBuilder
     */
    private function createBaseQueryBuilder(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.startingCity = :startingCity')
            ->setParameter('startingCity', $criteria['startingCity'])
            ->andWhere('r.arrivalCity = :arrivalCity')
            ->setParameter('arrivalCity', $criteria['arrivalCity'])
            ->leftJoin('r.passenger', 'p')
            ->groupBy('r.id')
            ->having('r.nbPlacesAvailable > COUNT(p.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', RideStatus::getDefaultStatus());

        $this->addOptionalFilters($qb, $criteria);

        return $qb;
    }

    /**
     * Ajoute les filtres optionnels à la requête
     * @param QueryBuilder $qb Le query builder
     * @param array $criteria Les critères
     * @return void
     */
    private function addOptionalFilters(QueryBuilder $qb, array $criteria): void
    {
        if (isset($criteria['maxPrice'])) {
            $qb->andWhere('r.price <= :maxPrice')
                ->setParameter('maxPrice', $criteria['maxPrice']);
        }

        if (isset($criteria['maxDuration'])) {
            $qb->andWhere('TIMESTAMPDIFF(MINUTE, r.startingAt, r.arrivalAt) <= :maxDuration')
                ->setParameter('maxDuration', $criteria['maxDuration']);
        }

        if (isset($criteria['MinDriverGrade'])) {
            $qb->leftJoin('r.driver', 'u')
                ->andWhere('u.grade >= :MinDriverGrade')
                ->setParameter('MinDriverGrade', $criteria['MinDriverGrade']);
        }


    }

    /**
     * Ajoute l'information sur le nombre de places restantes aux résultats
     * @param array $rides Les trajets
     * @return array Les trajets avec l'information sur les places restantes
     */
    private function addRemainingSeatsInfo(array $rides): array
    {
        $ridesWithRemainingSeats = [];
        foreach ($rides as $ride) {
            $remainingSeats = $ride->getNbPlacesAvailable() - $ride->getPassenger()->count();
            $ridesWithRemainingSeats[] = [
                'ride' => $ride,
                'remainingSeats' => $remainingSeats
            ];
        }
        return $ridesWithRemainingSeats;
    }
}
