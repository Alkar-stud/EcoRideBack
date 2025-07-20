<?php

namespace App\DataFixtures;

use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use DateTimeImmutable;
use Exception;

class VehicleFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $vehiclesData = [
            1 => [2, 'Aston Martin', 'DB5', 'Grise', 'BMT 216A', '1964-01-02', 'ALMOSTECO', 1, '2025-07-20 07:17:20', null],
            2 => [2, 'Citroën', '2 CV', 'Bleue', '123 AA 75', '1968-05-02', 'NOTECO', 3, '2025-07-20 07:17:20', null],
            3 => [2, 'Ford', 'Gran Torino', 'Rouge et blanche', '537 ONN', '1975-02-02', 'ECO', 3, '2025-07-20 07:17:20', null],
            4 => [2, 'Renault', 'R4', 'Blanche', '9999 ZZ 75', '1970-01-01', 'ECO', 4, '2025-07-20 07:17:20', null],
            5 => [4, 'Renault', 'Zoë', 'Rouge', 'ZOE', '2020-01-01', 'ECO', 3, '2025-07-20 11:16:19', null],
            6 => [4, 'Ford', 'FALCON “INTERCEPTOR” XB GT', 'Noir', 'MAX 079', '1973-01-01', 'NOTECO', 3, '2025-07-20 11:16:59', null],
            7 => [4, 'Wolkswagen', 'Combi', 'Vert', 'Bay Window', '1970-01-01', 'ALMOSTECO', 4, '2025-07-20 11:23:00', '2025-07-20 11:30:37'],
        ];

        foreach ($vehiclesData as $id => $v) {
            $vehicle = new Vehicle();
            $vehicle->setOwner($this->getReference('user_' . $v[0], \App\Entity\User::class));
            $vehicle->setBrand($v[1]);
            $vehicle->setModel($v[2]);
            $vehicle->setColor($v[3]);
            $vehicle->setLicensePlate($v[4]);
            $vehicle->setLicenseFirstDate(new \DateTime($v[5]));
            $vehicle->setEnergy($v[6]);
            $vehicle->setMaxNbPlacesAvailable($v[7]);
            $vehicle->setCreatedAt(new DateTimeImmutable($v[8]));
            $vehicle->setUpdatedAt($v[9] ? new DateTimeImmutable($v[9]) : null);
            $manager->persist($vehicle);
            $this->addReference('vehicle_' . $id, $vehicle);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PreferencesFixtures::class];
    }
}
