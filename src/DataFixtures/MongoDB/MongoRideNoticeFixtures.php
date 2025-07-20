<?php

namespace App\DataFixtures\MongoDB;

use App\Document\MongoRideNotice;
use DateTimeImmutable;
use Doctrine\Bundle\MongoDBBundle\Fixture\Fixture;
use Doctrine\Persistence\ObjectManager;

class MongoRideNoticeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $data = [
            [
                'status' => 'VALIDATED',
                'grade' => 10,
                'title' => 'Top !',
                'content' => 'Top !',
                'publishedBy' => 3,
                'relatedFor' => 1,
                'createdAt' => new DateTimeImmutable('2025-07-20 09:58:29'),
                'updatedAt' => new DateTimeImmutable('2025-07-20 10:03:03'),
                'validateBy' => 5,
            ],
            [
                'status' => 'VALIDATED',
                'grade' => 10,
                'title' => 'Au top !',
                'content' => 'Au top !',
                'publishedBy' => 4,
                'relatedFor' => 1,
                'createdAt' => new DateTimeImmutable('2025-07-20 09:58:53'),
                'updatedAt' => new DateTimeImmutable('2025-07-20 10:03:05'),
                'validateBy' => 5,
            ],
            [
                'status' => 'VALIDATED',
                'grade' => 2,
                'title' => "Pas de musique, l'autoradio était HS !",
                'content' => "Pas de musique, l'autoradio était HS !",
                'publishedBy' => 3,
                'relatedFor' => 2,
                'createdAt' => new DateTimeImmutable('2025-07-20 09:59:53'),
                'updatedAt' => new DateTimeImmutable('2025-07-20 10:03:07'),
                'validateBy' => 5,
            ],
            [
                'status' => 'VALIDATED',
                'grade' => 6,
                'title' => 'Pas de musique',
                'content' => 'Pas de musique',
                'publishedBy' => 4,
                'relatedFor' => 2,
                'createdAt' => new DateTimeImmutable('2025-07-20 10:02:35'),
                'updatedAt' => new DateTimeImmutable('2025-07-20 10:03:09'),
                'validateBy' => 5,
            ],
            [
                'status' => 'VALIDATED',
                'grade' => 10,
                'title' => 'ok',
                'content' => 'ok',
                'publishedBy' => 3,
                'relatedFor' => 10,
                'createdAt' => new DateTimeImmutable('2025-07-20 10:09:49'),
                'updatedAt' => new DateTimeImmutable('2025-07-20 10:10:04'),
                'validateBy' => 1,
            ],
        ];

        foreach ($data as $row) {
            $doc = new MongoRideNotice();
            $doc->setStatus($row['status']);
            $doc->setGrade($row['grade']);
            $doc->setTitle($row['title']);
            $doc->setContent($row['content']);
            $doc->setPublishedBy($row['publishedBy']);
            $doc->setRelatedFor($row['relatedFor']);
            $doc->setCreatedAt($row['createdAt']);
            $doc->setUpdatedAt($row['updatedAt']);
            $doc->setValidateBy($row['validateBy']);
            $doc->setRefusedBy(null);
            $manager->persist($doc);
        }
        $manager->flush();
    }
}