<?php

namespace App\Controller;

use App\Entity\Preferences;
use App\Entity\User;
use App\Repository\PreferencesRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/account/preferences', name: 'app_api_account_preferences_')]
final class PreferencesController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly PreferencesRepository  $repository,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $preferences = $this->serializer->deserialize($request->getContent(), Preferences::class, 'json');
        $preferences->setCreatedAt(new DateTimeImmutable());
        $preferences->setUser($user);

        // Convertir le champ "libelle" en camelCase
        $libelle = $preferences->getLibelle();
        $camelCaseLibelle = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $libelle))));
        $preferences->setLibelle($camelCaseLibelle);

        $this->manager->persist($preferences);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $preferences->getId(),
                'libelle'  => $preferences->getLibelle(),
                'description' => $preferences->getDescription(),
                'createdAt' => $preferences->getCreatedAt(),
                'updatedAt' => $preferences->getUpdatedAt(),
                'userId' => $preferences->getUser()->getId()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $preferences = $this->repository->findBy(['user' => $user->getId()]);

        if ($preferences) {
            $responseData = $this->serializer->serialize(
                $preferences,
                'json',
                ['groups' => ['preferences_user']]
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {

        $preferences = $this->repository->findOneBy(['id' => $id, 'user' => $user->getId()]);
        if ($preferences) {

            return new JsonResponse(
                [
                    'id'  => $preferences->getId(),
                    'libelle'  => $preferences->getLibelle(),
                    'description' => $preferences->getDescription(),
                    'createdAt' => $preferences->getCreatedAt(),
                    'updatedAt' => $preferences->getUpdatedAt(),
                    'userId' => $preferences->getUser()->getId()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse
    {
        $preferences = $this->repository->findOneBy(['id' => $id , 'user' => $user->getId()]);

        if ($preferences) {
            $preferences = $this->serializer->deserialize(
                $request->getContent(),
                Preferences::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $preferences]
            );
            $preferences->setUpdatedAt(new DateTimeImmutable());

            // Convertir le champ "libelle" en camelCase
            $libelle = $preferences->getLibelle();
            $camelCaseLibelle = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $libelle))));
            $preferences->setLibelle($camelCaseLibelle);

            $this->manager->flush();

            return new JsonResponse(
                [
                    'id'  => $preferences->getId(),
                    'libelle'  => $preferences->getLibelle(),
                    'description' => $preferences->getDescription(),
                    'createdAt' => $preferences->getCreatedAt(),
                    'updatedAt' => $preferences->getUpdatedAt(),
                    'userId' => $preferences->getUser()->getId()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $preferences = $this->repository->findOneBy(['id' => $id, 'user' => $user->getId()]);
        if ($preferences) {
            $this->manager->remove($preferences);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
