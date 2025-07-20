<?php

namespace App\DataFixtures;

use App\Entity\Preferences;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use DateTimeImmutable;
use Exception;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class PreferencesFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $prefsData = [
            [1, 2, 'smokingAllowed', 'no', '2025-07-20 07:17:20', null],
            [2, 2, 'petsAllowed', 'no', '2025-07-20 07:17:20', null],
            [3, 3, 'smokingAllowed', 'no', '2025-07-20 07:17:20', null],
            [4, 3, 'petsAllowed', 'no', '2025-07-20 07:17:20', null],
            [5, 4, 'smokingAllowed', 'no', '2025-07-20 07:17:20', null],
            [6, 4, 'petsAllowed', 'no', '2025-07-20 07:17:20', null],
            [7, 2, 'Musique', "J'écoute du métal", '2025-07-20 07:17:20', null],
            [8, 5, 'smokingAllowed', 'no', '2025-07-20 07:17:20', null],
            [9, 5, 'petsAllowed', 'no', '2025-07-20 07:17:20', null],
            [10, 6, 'smokingAllowed', 'no', '2025-07-20 07:17:20', null],
            [11, 6, 'petsAllowed', 'no', '2025-07-20 07:17:20', null],
        ];

        foreach ($prefsData as $p) {
            $pref = new Preferences();
            $pref->setUser($this->getReference('user_' . $p[1], \App\Entity\User::class));
            $pref->setLibelle($p[2]);
            $pref->setDescription($p[3]);
            $pref->setCreatedAt(new DateTimeImmutable($p[4]));
            $pref->setUpdatedAt($p[5] ? new DateTimeImmutable($p[5]) : null);
            $manager->persist($pref);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}