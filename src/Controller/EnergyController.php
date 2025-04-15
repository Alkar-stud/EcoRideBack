<?php

namespace App\Controller;

use App\Entity\Energy;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EnergyRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/energy', name: 'app_api_energy_')]
final class EnergyController extends AbstractController{

    private const ROUTE_ID = '/{id}';

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly EnergyRepository       $repository,
        private readonly SerializerInterface    $serializer,
        private readonly UrlGeneratorInterface  $urlGenerator,
    )
    {

    }
    #[Route('/add', name: 'add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function add(Request $request): JsonResponse
    {
        $energy = $this->serializer->deserialize($request->getContent(), Energy::class, 'json');
        $energy->setCreatedAt(new DateTimeImmutable());

        // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
        $libelle = $energy->getLibelle();
        $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
        $energy->setLibelle($formattedLibelle);

        $this->manager->persist($energy);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($energy, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_energy_show',
            ['id' => $energy->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse($responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    }
    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(): JsonResponse
    {
        $energies = $this->repository->findBy([], ['isEco' => 'DESC', 'libelle' => 'ASC']);

        if ($energies) {
            $responseData = $this->serializer->serialize(
                $energies,
                'json'
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route(self::ROUTE_ID, name: 'show', methods: 'GET')]
    public function showById(int $id): JsonResponse
    {

        $energy = $this->repository->findOneBy(['id' => $id]);
        if ($energy) {
            $responseData = $this->serializer->serialize($energy, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(int $id, Request $request): JsonResponse
    {
        $energy = $this->repository->findOneBy(['id' => $id]);

        if ($energy) {
            $energy = $this->serializer->deserialize(
                $request->getContent(),
                Energy::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $energy]
            );
            // Convertir le champ "libelle" pour qu'il ait une seule majuscule au premier caractère, même avec des accents
            $libelle = $energy->getLibelle();
            $formattedLibelle = mb_convert_case($libelle, MB_CASE_TITLE, "UTF-8");
            $energy->setLibelle($formattedLibelle);
            $energy->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            $responseData = $this->serializer->serialize($energy, 'json');
            $location = $this->urlGenerator->generate(
                'app_api_energy_show',
                ['id' => $energy->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            return new JsonResponse($responseData, Response::HTTP_OK, ["Location" => $location], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    #[Route(self::ROUTE_ID, name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $energy = $this->repository->findOneBy(['id' => $id]);
        if ($energy) {
            $this->manager->remove($energy);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }
}
