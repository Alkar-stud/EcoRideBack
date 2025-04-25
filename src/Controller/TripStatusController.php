<?php

namespace App\Controller;

use App\Entity\TripStatus;
use App\Repository\TripStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/api/trip/status', name: 'app_api_trip_status_')]
final class TripStatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly TripStatusRepository  $repository,
        private readonly SerializerInterface    $serializer,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function add(Request $request): JsonResponse
    {
        $tripStatus = $this->serializer->deserialize($request->getContent(), TripStatus::class, 'json');
        $tripStatus->setCreatedAt(new DateTimeImmutable());

        // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
        $libelle = $tripStatus->getLibelle();
        $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
        $tripStatus->setLibelle($formattedLibelle);

        $this->manager->persist($tripStatus);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $tripStatus->getId(),
                'libelle'  => $tripStatus->getLibelle(),
                'code'  => $tripStatus->getCode(),
                'createdAt' => $tripStatus->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list', name: 'showAll', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $tripStatus = $this->repository->findAll();

        if ($tripStatus) {
            $responseData = $this->serializer->serialize(
                $tripStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(['error' => true, 'message' => 'Aucun voyage trouvé.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(int $id): JsonResponse
    {
        $tripStatus = $this->repository->findOneBy(['id' => $id]);
        if ($tripStatus) {
            $responseData = $this->serializer->serialize(
                $tripStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $tripStatus = $this->repository->findOneBy(['id' => $id]);

        if ($tripStatus) {
            $tripStatus = $this->serializer->deserialize(
                $request->getContent(),
                TripStatus::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $tripStatus]
            );
            // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
            $libelle = $tripStatus->getLibelle();
            $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
            $tripStatus->setLibelle($formattedLibelle);
            $tripStatus->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            $responseData = $this->serializer->serialize(
                $tripStatus,
                'json'
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $tripStatus = $this->repository->findOneBy(['id' => $id]);
        if ($tripStatus) {
            $this->manager->remove($tripStatus);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(['error' => true, 'message' => 'Ce statut n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

}
