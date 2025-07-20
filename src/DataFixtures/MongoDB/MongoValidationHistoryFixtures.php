<?php

namespace App\DataFixtures\MongoDB;

use App\Document\MongoValidationHistory;
use DateTimeImmutable;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Persistence\ObjectManager;

class MongoValidationHistoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'rideId' => 2,
                'validationId' => 3,
                'user' => 5,
                'addContent' => '[20/07/2025 12:01:01] Mail au chauffeur',
                'createdAt' => new DateTimeImmutable('2025-07-20 12:01:01'),
            ],
            [
                'rideId' => 2,
                'validationId' => 3,
                'user' => 5,
                'addContent' => "[20/07/2025 12:01:01] Mail au chauffeur\n[20/07/2025 12:01:27] Effectivement, autoradio HS, il en a acheté un autre depuis",
                'isClosed' => true,
                'closedBy' => 5,
                'createdAt' => new DateTimeImmutable('2025-07-20 12:01:27'),
            ],
        ];

        foreach ($data as $row) {
            $doc = new MongoValidationHistory();
            $doc->setRideId($row['rideId']);
            $doc->setValidationId($row['validationId']);
            $doc->setUser($row['user']);
            $doc->setAddContent($row['addContent']);
            if (isset($row['isClosed'])) {
                $doc->setIsClosed($row['isClosed']);
            }
            if (isset($row['closedBy'])) {
                $doc->setClosedBy($row['closedBy']);
            }
            $doc->setCreatedAt($row['createdAt']);
            $manager->persist($doc);
        }
        $manager->flush();
    }
}