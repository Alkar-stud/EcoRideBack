<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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
            return new JsonResponse(['error' => 'Le nombre de places doit être au minimum de 1.', 'field' => 'nbPlace'], Response::HTTP_BAD_REQUEST);
        }
        //La marque est obligatoire.
        if ($vehicle->getBrand() === null || $vehicle->getBrand() === '') {
            return new JsonResponse(['error' => 'Le marque est obligatoire.', 'field' => 'brand'], Response::HTTP_BAD_REQUEST);
        }
        //Le modèle est obligatoire.
        if ($vehicle->getModel() === null || $vehicle->getModel() === '') {
            return new JsonResponse(['error' => 'Le modèle est obligatoire.', 'field' => 'model'], Response::HTTP_BAD_REQUEST);
        }
        //La couleur est obligatoire.
        if ($vehicle->getColor() === null || $vehicle->getColor() === '') {
            return new JsonResponse(['error' => 'La couleur est obligatoire.', 'field' => 'color'], Response::HTTP_BAD_REQUEST);
        }
        //L'immatriculation est obligatoire.
        if ($vehicle->getRegistration() === null || $vehicle->getRegistration() === '') {
            return new JsonResponse(['error' => 'L\'immatriculation est obligatoire.', 'field' => 'registration'], Response::HTTP_BAD_REQUEST);
        }
        //La date de première immatriculation est obligatoire.
        if ($vehicle->getRegistrationFirstDate() === null || $vehicle->getRegistrationFirstDate() === '') {
            return new JsonResponse(['error' => 'La date d\'immatriculation est obligatoire.', 'field' => 'registrationFirstDate'], Response::HTTP_BAD_REQUEST);
        }
        // Récupérer l'identifiant de l'énergie depuis le JSON
        $data = json_decode($request->getContent(), true);
        $energyId = $data['energy'] ?? null;
        if (!$energyId) {
            return new JsonResponse(['error' => 'Il faut choisir une motorisation !', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier et associer l'énergie
        $energy = $this->energyRepository->find($energyId);
        if (!$energy) {
            return new JsonResponse(['error' => 'Ce carburant n\'existe pas', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }
        $vehicle->setEnergy($energy);

        // Associer le propriétaire (Owner) à l'utilisateur actuel
        $user = $this->getUser();
        $vehicle->setOwner($user);

        $this->manager->persist($vehicle);
        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $vehicle->getId(),
                'libelle'  => $vehicle->getBrand(),
                'description' => $vehicle->getModel(),
                'color' => $vehicle->getColor(),
                'registration' => $vehicle->getRegistration(),
                'registrationFirstDate' => $vehicle->getRegistrationFirstDate(),
                'nbPlace' => $vehicle->getNbPlace(),
                'energy' => $vehicle->getEnergyDetails(),
                'createdAt' => $vehicle->getCreatedAt()
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/list/', name: 'showAll', methods: 'GET')]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $vehicles = $this->repository->findBy(['owner' => $user->getId()]);

        if ($vehicles) {
            $responseData = $this->serializer->serialize(
                $vehicles,
                'json',
                ['groups' => ['vehicle_details']]
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

            return new JsonResponse(
                [
                    'id'  => $vehicle->getId(),
                    'libelle'  => $vehicle->getBrand(),
                    'description' => $vehicle->getModel(),
                    'color' => $vehicle->getColor(),
                    'registration' => $vehicle->getRegistration(),
                    'registrationFirstDate' => $vehicle->getRegistrationFirstDate(),
                    'nbPlace' => $vehicle->getNbPlace(),
                    'energy' => $vehicle->getEnergyDetails(),
                    'createdAt' => $vehicle->getCreatedAt(),
                    'updateAt' => $vehicle->getUpdatedAt()
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse | RedirectResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $vehicle->setUpdatedAt(new DateTimeImmutable());

        // Vérifier que le nombre de places est au moins égal à 1.
        if ($vehicle->getNbPlace() < 1) {
            return new JsonResponse(['error' => 'Le nombre de places doit être au minimum de 1.', 'field' => 'nbPlace'], Response::HTTP_BAD_REQUEST);
        }
        //La marque est obligatoire.
        if ($vehicle->getBrand() === null || $vehicle->getBrand() === '') {
            return new JsonResponse(['error' => 'Le marque est obligatoire.', 'field' => 'brand'], Response::HTTP_BAD_REQUEST);
        }
        //Le modèle est obligatoire.
        if ($vehicle->getModel() === null || $vehicle->getModel() === '') {
            return new JsonResponse(['error' => 'Le modèle est obligatoire.', 'field' => 'model'], Response::HTTP_BAD_REQUEST);
        }
        //La couleur est obligatoire.
        if ($vehicle->getColor() === null || $vehicle->getColor() === '') {
            return new JsonResponse(['error' => 'La couleur est obligatoire.', 'field' => 'color'], Response::HTTP_BAD_REQUEST);
        }
        //L'immatriculation est obligatoire.
        if ($vehicle->getRegistration() === null || $vehicle->getRegistration() === '') {
            return new JsonResponse(['error' => 'L\'immatriculation est obligatoire.', 'field' => 'registration'], Response::HTTP_BAD_REQUEST);
        }
        //La date de première immatriculation est obligatoire.
        if ($vehicle->getRegistrationFirstDate() === null || $vehicle->getRegistrationFirstDate() === '') {
            return new JsonResponse(['error' => 'La date d\'immatriculation est obligatoire.', 'field' => 'registrationFirstDate'], Response::HTTP_BAD_REQUEST);
        }
        // Récupérer l'identifiant de l'énergie depuis le JSON
        $data = json_decode($request->getContent(), true);
        $energyId = $data['energy'] ?? null;
        if (!$energyId) {
            return new JsonResponse(['error' => 'Il faut choisir une motorisation !', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier et associer l'énergie
        $energy = $this->energyRepository->find($energyId);
        if (!$energy) {
            return new JsonResponse(['error' => 'Ce carburant n\'existe pas', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }
        $vehicle->setEnergy($energy);

        $this->manager->flush();

        return new JsonResponse(
            [
                'id'  => $vehicle->getId(),
                'libelle'  => $vehicle->getBrand(),
                'description' => $vehicle->getModel(),
                'color' => $vehicle->getColor(),
                'registration' => $vehicle->getRegistration(),
                'registrationFirstDate' => $vehicle->getRegistrationFirstDate(),
                'nbPlace' => $vehicle->getNbPlace(),
                'energy' => $vehicle->getEnergyDetails(),
                'createdAt' => $vehicle->getCreatedAt(),
                'updateAt' => $vehicle->getUpdatedAt()
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($vehicle) {
            $this->manager->remove($vehicle);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
    }
}
