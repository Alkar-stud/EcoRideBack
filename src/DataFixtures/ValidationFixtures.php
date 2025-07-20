<?php

namespace App\DataFixtures;

use App\Entity\Validation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use DateTimeImmutable;
use Exception;

class ValidationFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        $validations = [
            [1, 3, 1, 3, true, null, true, '2025-07-20 11:58:23', null, null, null],
            [2, 4, 1, 4, true, null, true, '2025-07-20 11:58:45', null, null, null],
            [3, 3, 2, 5, false, "Pas de musique, l'autoradio était HS !", true, '2025-07-20 11:59:46', '2025-07-20 12:01:27', 5, '[20/07/2025 12:01:01] Mail au chauffeur\n[20/07/2025 12:01:27] Effectivement, autoradio HS, il en a acheté un autre depuis'],
            [4, 4, 2, 4, true, null, true, '2025-07-20 12:02:29', null, null, null],
            [5, 3, 10, 3, true, null, true, '2025-07-20 12:09:44', null, null, null],
        ];

        foreach ($validations as $v) {
            $validation = new Validation();
            $validation->setPassenger($this->getReference('user_' . $v[1], \App\Entity\User::class));
            $validation->setRide($this->getReference('ride_' . $v[2], \App\Entity\Ride::class));
            $validation->setClosedBy($this->getReference('user_' . $v[3], \App\Entity\User::class));
            $validation->setIsAllOk($v[4]);
            $validation->setContent($v[5]);
            $validation->setIsClosed($v[6]);
            $validation->setCreatedAt(new DateTimeImmutable($v[7]));
            $validation->setUpdatedAt($v[8] ? new DateTimeImmutable($v[8]) : null);
            if ($v[9]) $validation->setSupportBy($this->getReference('user_' . $v[9], \App\Entity\User::class));
            $validation->setCloseContent($v[10]);
            $manager->persist($validation);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RideUserFixtures::class];
    }
}
