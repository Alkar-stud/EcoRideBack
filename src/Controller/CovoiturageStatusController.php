<?php

namespace App\Controller;

use App\Entity\CovoiturageStatus;
use App\Repository\CovoiturageStatusRepository;
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

#[Route('/api/covoiturage/status', name: 'app_api_covoiturage_status_')]
final class CovoiturageStatusController extends AbstractController{
    private const ROUTE_ID = '/{id}';

    public function __construct(
        private readonly EntityManagerInterface         $manager,
        private readonly CovoiturageStatusRepository    $repository,
        private readonly SerializerInterface            $serializer,
    )
    {

    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function add(Request $request): JsonResponse
    {
        $covoituragestatus = $this->serializer->deserialize($request->getContent(), CovoiturageStatus::class, 'json');
        $covoituragestatus->setCreatedAt(new DateTimeImmutable());

        // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
        $libelle = $covoituragestatus->getLibelle();
        $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
        $covoituragestatus->setLibelle($formattedLibelle);

        $this->manager->persist($covoituragestatus);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $covoituragestatus->getId(),
                'libelle'  => $covoituragestatus->getLibelle(),
                'createdAt' => $covoituragestatus->getCreatedAt()
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $covoituragestatus = $this->repository->findBy([], ['libelle' => 'ASC']);

        if ($covoituragestatus) {
            $responseData = $this->serializer->serialize(
                $covoituragestatus,
                'json'
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route(self::ROUTE_ID, name: 'show', methods: 'GET')]
    public function showById(int $id): JsonResponse
    {

        $covoituragestatus = $this->repository->findOneBy(['id' => $id]);
        if ($covoituragestatus) {
            return new JsonResponse(
                [
                    'id'  => $covoituragestatus->getId(),
                    'libelle'  => $covoituragestatus->getLibelle(),
                    'createdAt' => $covoituragestatus->getCreatedAt(),
                    'updateAt' => $covoituragestatus->getUpdatedAt()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $covoituragestatus = $this->repository->findOneBy(['id' => $id]);

        if ($covoituragestatus) {
            $covoituragestatus = $this->serializer->deserialize(
                $request->getContent(),
                CovoiturageStatus::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $covoituragestatus]
            );
            // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
            $libelle = $covoituragestatus->getLibelle();
            $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
            $covoituragestatus->setLibelle($formattedLibelle);
            $covoituragestatus->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            return new JsonResponse(
                [
                    'id'  => $covoituragestatus->getId(),
                    'libelle'  => $covoituragestatus->getLibelle(),
                    'createdAt' => $covoituragestatus->getCreatedAt(),
                    'updateAt' => $covoituragestatus->getUpdatedAt()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route(self::ROUTE_ID, name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $covoituragestatus = $this->repository->findOneBy(['id' => $id]);
        if ($covoituragestatus) {
            $this->manager->remove($covoituragestatus);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }

}
