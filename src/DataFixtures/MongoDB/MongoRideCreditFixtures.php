<?php

namespace App\DataFixtures\MongoDB;

use App\Document\MongoRideCredit;
use DateTimeImmutable;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Persistence\ObjectManager;

class MongoRideCreditFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'rideId' => 1,
                'user' => 4,
                'addCredit' => 45,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:39:05'),
            ],
            [
                'rideId' => 2,
                'user' => 4,
                'addCredit' => 45,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:54:10'),
            ],
            [
                'rideId' => 1,
                'user' => 3,
                'addCredit' => 45,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:54:42'),
            ],
            [
                'rideId' => 2,
                'user' => 3,
                'addCredit' => 45,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:54:50'),
            ],
            [
                'rideId' => 1,
                'user' => 2,
                'addCredit' => 0,
                'withdrawCredit' => 2,
                'reason' => 'Commission de la plateforme pour le covoiturage 1',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:58:45'),
            ],
            [
                'rideId' => 1,
                'user' => 2,
                'addCredit' => 0,
                'withdrawCredit' => 88,
                'reason' => 'Paiement du chauffeur pour le covoiturage 1',
                'createdDate' => new DateTimeImmutable('2025-07-20 09:58:46'),
            ],
            [
                'rideId' => 2,
                'user' => 2,
                'addCredit' => 0,
                'withdrawCredit' => 2,
                'reason' => 'Commission de la plateforme pour le covoiturage 2',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:02:29'),
            ],
            [
                'rideId' => 2,
                'user' => 2,
                'addCredit' => 0,
                'withdrawCredit' => 88,
                'reason' => 'Paiement du chauffeur pour le covoiturage 2',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:02:29'),
            ],
            [
                'rideId' => 10,
                'user' => 3,
                'addCredit' => 50,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:09:15'),
            ],
            [
                'rideId' => 10,
                'user' => 4,
                'addCredit' => 0,
                'withdrawCredit' => 2,
                'reason' => 'Commission de la plateforme pour le covoiturage 10',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:09:44'),
            ],
            [
                'rideId' => 10,
                'user' => 4,
                'addCredit' => 0,
                'withdrawCredit' => 48,
                'reason' => 'Paiement du chauffeur pour le covoiturage 10',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:09:44'),
            ],
            [
                'rideId' => 5,
                'user' => 4,
                'addCredit' => 80,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:12:41'),
            ],
            [
                'rideId' => 6,
                'user' => 4,
                'addCredit' => 40,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:14:20'),
            ],
            [
                'rideId' => 5,
                'user' => 3,
                'addCredit' => 80,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:14:35'),
            ],
            [
                'rideId' => 6,
                'user' => 3,
                'addCredit' => 40,
                'withdrawCredit' => 0,
                'reason' => 'addPassenger',
                'createdDate' => new DateTimeImmutable('2025-07-20 10:16:15'),
            ],
        ];

        foreach ($data as $row) {
            $doc = new MongoRideCredit();
            $doc->setRideId($row['rideId']);
            $doc->setUser($row['user']);
            $doc->setAddCredit($row['addCredit']);
            $doc->setWithdrawCredit($row['withdrawCredit']);
            $doc->setReason($row['reason']);
            $doc->setCreatedDate($row['createdDate']);
            $manager->persist($doc);
        }
        $manager->flush();
    }
}