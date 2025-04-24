<?php

namespace App\Controller;

use App\Entity\EcoRide;
use App\Repository\EcoRideRepository;
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

#[Route('/api/ecoride', name: 'app_api_ecoride_')]
final class EcoRideController extends AbstractController{
    private const ROUTE_ID = '/{id}';

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly EcoRideRepository       $repository,
        private readonly SerializerInterface    $serializer,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $ecoride = $this->serializer->deserialize($request->getContent(), EcoRide::class, 'json');
        $ecoride->setCreatedAt(new DateTimeImmutable());

        // Convertir le champ "libelle" pour qu'il soit en majuscule, tout autre caractère remplacé par _.
        $libelle = $ecoride->getLibelle();

        // Vérifier que le champ "libelle" n'est ni vide ni null
        if (empty($libelle)) {
            return new JsonResponse(['error' => 'Le champ libelle ne peut pas être vide ou null.'], Response::HTTP_BAD_REQUEST);
        }

        $formattedLibelle = mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '_', $libelle), "UTF-8");
        $ecoride->setLibelle($formattedLibelle);

        $this->manager->persist($ecoride);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $ecoride->getId(),
                'libelle'  => $ecoride->getLibelle(),
                'parameters' => $ecoride->getParameters(),
                'createdAt' => $ecoride->getCreatedAt()
            ],
            Response::HTTP_OK
        );
    }


    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $ecorides = $this->repository->findBy([], ['libelle' => 'ASC']);

        if ($ecorides) {
            $responseData = $this->serializer->serialize(
                $ecorides,
                'json'
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route(self::ROUTE_ID, name: 'show', methods: 'GET')]
    public function showById(int $id): JsonResponse
    {

        $ecoride = $this->repository->findOneBy(['id' => $id]);
        if ($ecoride) {
            return new JsonResponse(
                [
                    'id'  => $ecoride->getId(),
                    'libelle'  => $ecoride->getLibelle(),
                    'parameters' => $ecoride->getParameters(),
                    'createdAt' => $ecoride->getCreatedAt(),
                    'updateAt' => $ecoride->getUpdatedAt()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $ecoride = $this->repository->findOneBy(['id' => $id]);

        if ($ecoride) {
            // Empêcher la modification du champ "libelle"
            $originalLibelle = $ecoride->getLibelle();
            $ecoride = $this->serializer->deserialize(
                $request->getContent(),
                EcoRide::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $ecoride]
            );
            $ecoride->setLibelle($originalLibelle);

            $ecoride->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            return new JsonResponse(
                [
                    'id'  => $ecoride->getId(),
                    'libelle'  => $ecoride->getLibelle(),
                    'parameters' => $ecoride->getParameters(),
                    'createdAt' => $ecoride->getCreatedAt(),
                    'updateAt' => $ecoride->getUpdatedAt()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    #[Route(self::ROUTE_ID, name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $ecoride = $this->repository->findOneBy(['id' => $id]);
        if ($ecoride) {
            $this->manager->remove($ecoride);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }

}
