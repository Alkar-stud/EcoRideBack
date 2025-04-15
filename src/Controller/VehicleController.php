<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Energy;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VehicleRepository;
use App\Repository\EnergyRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/vehicle', name: 'app_api_vehicle_')]
final class VehicleController extends AbstractController{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly VehicleRepository      $repository,
        private readonly EnergyRepository       $energyRepository,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse | RedirectResponse
    {
        $vehicle = $this->serializer->deserialize($request->getContent(), Vehicle::class, 'json');
        $vehicle->setCreatedAt(new DateTimeImmutable());

        // Vérifier que le nombre de places est au moins égal à 1.
        if ($vehicle->getNbPlace() < 1) {
            return new JsonResponse(['error' => 'Le nombre de places doit être au minimum de 1.'], Response::HTTP_BAD_REQUEST);
        }

        // Récupérer l'identifiant de l'énergie depuis le JSON
        $data = json_decode($request->getContent(), true);
        $energyId = $data['energy'] ?? null;
        if (!$energyId) {
            return new JsonResponse(['error' => 'Energy ID is required'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier et associer l'énergie
        $energy = $this->energyRepository->find($energyId);
        if (!$energy) {
            return new JsonResponse(['error' => 'Invalid energy ID'], Response::HTTP_BAD_REQUEST);
        }
        $vehicle->setEnergy($energy);

        // Associer le propriétaire (Owner) à l'utilisateur actuel
        $user = $this->getUser();
        $vehicle->setOwner($user);

        $this->manager->persist($vehicle);
        $this->manager->flush();


        // Redirection vers la route showById avec l'ID créé
        return $this->redirectToRoute('app_api_vehicle_show', ['id' => $vehicle->getId()]);
    }

    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $vehicles = $this->repository->findBy(['owner' => $user->getId()]);

        if ($vehicles) {
            $responseData = $this->serializer->serialize(
                $vehicles,
                'json',
                ['groups' => ['vehicle_basic']]
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($vehicle) {
            $responseData = $this->serializer->serialize(
                $vehicle,
                'json',
                ['groups' => ['vehicle_details']]
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

}
