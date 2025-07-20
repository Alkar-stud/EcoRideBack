<?php

namespace App\DataFixtures\MongoDB;

use App\Document\MongoEcoRideCreditsTemp;
use DateTimeImmutable;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Persistence\ObjectManager;

class MongoEcoRideCreditsTempFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'libelle' => 'TOTAL_CREDIT_TEMP',
                'valeur' => 240,
                'updatedDate' => new DateTimeImmutable('2025-07-20 10:16:15'),
            ],
        ];

        foreach ($data as $row) {
            $doc = new MongoEcoRideCreditsTemp();
            $doc->setLibelle($row['libelle']);
            $doc->setValeur($row['valeur']);
            $doc->setUpdatedDate($row['updatedDate']);
            $manager->persist($doc);
        }
        $manager->flush();
    }
}