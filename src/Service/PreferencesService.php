<?php

namespace App\Service;

use App\Entity\Preferences;
use App\Entity\User;
use App\Repository\PreferencesRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

readonly class PreferencesService
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PreferencesRepository  $repository,
        private SerializerInterface    $serializer
    ) {}

    public function addPreference(?User $user, Request $request): array
    {
        $preferences = $this->serializer->deserialize($request->getContent(), Preferences::class, 'json');
        $preferences->setCreatedAt(new DateTimeImmutable());
        $preferences->setUser($user);

        $this->manager->persist($preferences);
        $this->manager->flush();

        return [
            'id' => $preferences->getId(),
            'libelle' => $preferences->getLibelle(),
            'description' => $preferences->getDescription(),
            'createdAt' => $preferences->getCreatedAt(),
            'userId' => $preferences->getUser()->getId()
        ];
    }

    public function getAllPreferences(?User $user): ?string
    {
        $preferences = $this->repository->findBy(['user' => $user->getId()]);

        if ($preferences) {
            return $this->serializer->serialize(
                $preferences,
                'json',
                ['groups' => ['user_account']]
            );
        }

        return null;
    }

    public function editPreference(?User $user, int $id, Request $request): array
    {
        $preferences = $this->repository->findOneBy(['id' => $id, 'user' => $user->getId()]);

        if (!$preferences) {
            return ['error' => true, 'message' => 'Cette préférence n\'existe pas.'];
        }

        $preferences = $this->serializer->deserialize(
            $request->getContent(),
            Preferences::class,
            'json',
            ['object_to_populate' => $preferences]
        );

        $preferences->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return ['message' => 'Préférence modifiée avec succès'];
    }

    public function deletePreference(?User $user, int $id): array
    {
        $preferences = $this->repository->findOneBy(['id' => $id, 'user' => $user->getId()]);

        if (!$preferences) {
            return ['message' => 'Cette préférence n\'existe pas.', 'status' => Response::HTTP_NOT_FOUND];
        }

        if ($preferences->getLibelle() === 'smokingAllowed' || $preferences->getLibelle() === 'petsAllowed') {
            return ['message' => 'Cette préférence ne peut pas être supprimée.', 'status' => Response::HTTP_BAD_REQUEST];
        }

        $this->manager->remove($preferences);
        $this->manager->flush();

        return [];
    }
}
