<?php

namespace App\Service;

use MongoDB\Client;

class CovoiturageMongoService
{
    private \MongoDB\Collection $collection;

    public function __construct(string $mongoUri, string $databaseName)
    {
        $client = new Client($mongoUri);
        $this->collection = $client->selectCollection($databaseName, 'covoiturage');
    }

    public function add(array $data): void
    {
        $this->collection->insertOne($data);
    }
}