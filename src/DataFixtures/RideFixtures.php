<?php

namespace App\DataFixtures;

use App\Entity\Ride;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use DateTimeImmutable;
use Exception;

class RideFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $ridesData = [
            [1, 2, 2, 'Cour de la Gare', '51100', 'Reims', 'Place Kléber', '67000', 'Strasbourg', '2025-07-20 12:00:00', '2025-07-20 17:00:00', 45, 3, '2025-07-20 11:55:09', '2025-07-20 11:57:53', 'FINISHED', '2025-07-20 11:13:47', null],
            [2, 2, 2, 'Place Kléber', '67000', 'Strasbourg', 'Cour de la Gare', '51100', 'Reims', '2025-07-20 22:00:00', '2025-07-21 03:00:00', 45, 3, '2025-07-20 11:59:13', '2025-07-20 11:59:18', 'FINISHED', '2025-07-20 11:15:47', null],
            [3, 2, 1, 'Cour de la Gare', '51100', 'Reims', 'Quai Jacques Chirac', '75015', 'Paris', '2025-07-28 08:00:00', '2025-07-28 09:00:00', 25, 1, null, null, 'COMING', '2025-07-20 11:23:36', null],
            [4, 2, 1, 'Quai Jacques Chirac', '75015', 'Paris', 'Cour de la Gare', '51100', 'Reims', '2025-07-28 20:00:00', '2025-07-28 21:00:00', 25, 1, null, null, 'COMING', '2025-07-20 11:23:58', null],
            [5, 2, 3, 'Cour de la Gare', '51100', 'Reims', 'Square de narvik', '13001', 'Marseille', '2025-07-29 08:00:00', '2025-07-29 18:00:00', 80, 3, null, null, 'COMING', '2025-07-20 11:24:42', null],
            [6, 2, 3, 'Square de narvik', '13001', 'Marseille', 'Cours de Verdun Gensoul', '69002', 'Lyon', '2025-07-29 20:00:00', '2025-07-30 01:00:00', 40, 3, null, null, 'COMING', '2025-07-20 11:25:16', null],
            [7, 2, 4, 'Cours de Verdun Gensoul', '69002', 'Lyon', 'Quai Jacques Chirac', '75015', 'Paris', '2025-07-30 15:00:00', '2025-07-30 20:00:00', 50, 4, null, null, 'COMING', '2025-07-20 11:25:49', null],
            [8, 2, 3, 'Quai Jacques Chirac', '75015', 'Paris', '8 place du 19eme R.I.', '29200', 'Brest', '2025-08-01 08:00:00', '2025-08-01 13:00:00', 50, 3, null, null, 'COMING', '2025-07-20 11:27:20', null],
            [9, 2, 4, '8 place du 19eme R.I.', '29200', 'Brest', 'Place Kléber', '67000', 'Strasbourg', '2025-08-02 08:00:00', '2025-08-02 20:00:00', 120, 4, null, null, 'COMING', '2025-07-20 11:27:52', null],
            [10, 4, 5, 'Cour de la Gare', '51100', 'Reims', 'Cours de Verdun Gensoul', '69002', 'Lyon', '2025-07-20 15:00:00', '2025-07-20 20:00:00', 50, 3, '2025-07-20 12:09:23', '2025-07-20 12:09:26', 'FINISHED', '2025-07-20 11:28:52', null],
            [11, 4, 7, 'Cours de Verdun Gensoul', '69002', 'Lyon', 'Quai Jacques Chirac', '75015', 'Paris', '2025-08-04 09:00:00', '2025-08-04 15:00:00', 55, 4, null, null, 'COMING', '2025-07-20 11:29:46', null],
            [12, 4, 6, 'Quai Jacques Chirac', '75015', 'Paris', 'Square de narvik', '13001', 'Marseille', '2025-08-06 08:00:00', '2025-08-06 12:00:00', 40, 3, null, null, 'COMING', '2025-07-20 11:30:26', null],
            [13, 4, 6, 'Square de narvik', '13001', 'Marseille', 'Cour de la Gare', '51100', 'Reims', '2025-08-07 08:00:00', '2025-08-07 14:00:00', 60, 3, null, null, 'COMING', '2025-07-20 12:15:27', null],
        ];

        foreach ($ridesData as $r) {
            $ride = new Ride();
            $ride->setDriver($this->getReference('user_' . $r[1], \App\Entity\User::class));
            $ride->setVehicle($this->getReference('vehicle_' . $r[2], \App\Entity\Vehicle::class));
            $ride->setStartingStreet($r[3]);
            $ride->setStartingPostCode($r[4]);
            $ride->setStartingCity($r[5]);
            $ride->setArrivalStreet($r[6]);
            $ride->setArrivalPostCode($r[7]);
            $ride->setArrivalCity($r[8]);
            $ride->setStartingAt(new DateTimeImmutable($r[9]));
            $ride->setArrivalAt(new DateTimeImmutable($r[10]));
            $ride->setPrice($r[11]);
            $ride->setNbPlacesAvailable($r[12]);
            $ride->setActualDepartureAt($r[13] ? new DateTimeImmutable($r[13]) : null);
            $ride->setActualArrivalAt($r[14] ? new DateTimeImmutable($r[14]) : null);
            $ride->setStatus($r[15]);
            $ride->setCreatedAt(new DateTimeImmutable($r[16]));
            $ride->setUpdatedAt($r[17] ? new DateTimeImmutable($r[17]) : null);
            $this->addReference('ride_' . $r[0], $ride);
            $manager->persist($ride);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [VehicleFixtures::class];
    }

}
