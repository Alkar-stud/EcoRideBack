<?php

namespace App\Service;

use DateTimeInterface;
use MongoDB\Client;
use MongoDB\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CovoiturageMongoService
{
    private Collection $collection;

    public function __construct(string $mongoUri, string $databaseName)
    {
        $client = new Client($mongoUri);
        $this->collection = $client->selectCollection($databaseName, 'covoiturage');
    }

    private function convertDatesToIsoFormat(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $data[$key] = $value->format(DateTimeInterface::ATOM);
            } elseif (is_array($value)) {
                $data[$key] = $this->convertDatesToIsoFormat($value);
            }
        }

        return $data;
    }

    public function add(array $data): void
    {
        // Conversion des dates en format ISO 8601 avant insertion
        $data = $this->convertDatesToIsoFormat($data);
        $this->collection->insertOne($data);
    }

    public function update(int $id, array $data): JsonResponse
    {
        try {
            // Conversion des dates en format ISO 8601 avant mise à jour
            $data = $this->convertDatesToIsoFormat($data);
            $result = $this->collection->updateOne(
                ['id_covoiturage' => $id], // Correction de la clé pour correspondre à celle utilisée dans MongoDB
                ['$set' => $data]
            );

            if ($result->getMatchedCount() === 0) {
                return new JsonResponse([
                    'error' => 'Covoiturage introuvable dans MongoDB.'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'message' => 'Covoiturage mis à jour avec succès dans MongoDB.'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la mise à jour : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['id_covoiturage' => $id]);

            return $result->getDeletedCount() > 0;

        } catch (\Exception $e) {
            error_log("mongodb_delete_exception: Erreur lors de la suppression de l'id $id - " . $e->getMessage());
            return false;
        }
    }

    public function findById(int $id): ?array
    {
        $document = $this->collection->findOne(['id_covoiturage' => $id]);

        return $document?->getArrayCopy();
    }
}
