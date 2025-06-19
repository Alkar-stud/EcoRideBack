<?php

namespace App\DataFixtures;

use App\Entity\Ride;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class RidesFixtures extends Fixture
{
    private UserRepository $userRepository;
    private VehicleRepository $vehicleRepository;

    public function __construct(UserRepository $userRepository, VehicleRepository $vehicleRepository)
    {
        $this->userRepository = $userRepository;
        $this->vehicleRepository = $vehicleRepository;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Récupérer tous les utilisateurs qui sont conducteurs
        $drivers = $this->userRepository->findBy(['isDriver' => true]);

        if (empty($drivers)) {
            echo "Aucun conducteur trouvé dans la base de données.\n";
            return;
        }

        $statuses = ['COMING', 'CANCELED', 'FINISHED'];
        $totalRidesToCreate = 50;
        $driverCount = count($drivers);

        // Calculer combien de trajets par conducteur pour atteindre ~50 trajets
        $ridesPerDriver = max(1, ceil($totalRidesToCreate / $driverCount));

        $createdRides = 0;

        // Créer plusieurs trajets pour chaque conducteur
        foreach ($drivers as $driver) {
            // Récupérer les véhicules du conducteur
            $vehicles = $this->vehicleRepository->findBy(['owner' => $driver]);

            if (empty($vehicles)) {
                echo "Aucun véhicule trouvé pour le conducteur {$driver->getId()}.\n";
                continue;
            }

            for ($i = 0; $i < $ridesPerDriver && $createdRides < $totalRidesToCreate; $i++) {
                $ride = new Ride();
                $ride->setDriver($driver);

                // Sélectionner un véhicule aléatoire parmi ceux du conducteur
                $vehicle = $vehicles[array_rand($vehicles)];
                $ride->setVehicle($vehicle);

                // Adresse de départ
                $ride->setStartingStreet($faker->streetAddress);
                $ride->setStartingPostCode($faker->postcode);
                $ride->setStartingCity($faker->city);

                // Adresse d'arrivée
                $ride->setArrivalStreet($faker->streetAddress);
                $ride->setArrivalPostCode($faker->postcode);
                $ride->setArrivalCity($faker->city);

                // Dates de départ et d'arrivée
                $departureDate = $faker->dateTimeBetween('now', '+2 months');
                $startingAt = DateTimeImmutable::createFromMutable($departureDate);
                $ride->setStartingAt($startingAt);

                $arrivalDate = clone $departureDate;
                $arrivalDate->modify('+' . rand(30, 180) . ' minutes');
                $arrivalAt = DateTimeImmutable::createFromMutable($arrivalDate);
                $ride->setArrivalAt($arrivalAt);

                // Prix et places disponibles
                $ride->setPrice($faker->numberBetween(5, 50)); // en euro (5€ à 50€)
                $ride->setNbPlacesAvailable(rand(1, 6));

                // Statut
                $ride->setStatus($statuses[array_rand($statuses)]);

                // Dates de création/modification
                $ride->setCreatedAt(new DateTimeImmutable());

                $manager->persist($ride);
                $createdRides++;
            }
        }

        echo "Total de {$createdRides} trajets créés.\n";
        $manager->flush();
    }
}
