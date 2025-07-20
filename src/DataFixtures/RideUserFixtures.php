<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RideUserFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $rideUsers = [
            [1, 3], [1, 4], [2, 3], [2, 4], [5, 3], [5, 4], [6, 3], [6, 4], [10, 3]
        ];

        foreach ($rideUsers as [$rideId, $userId]) {
            $ride = $this->getReference('ride_' . $rideId, \App\Entity\Ride::class);
            $user = $this->getReference('user_' . $userId, \App\Entity\User::class);
            $ride->addPassenger($user);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RideFixtures::class];
    }
}
