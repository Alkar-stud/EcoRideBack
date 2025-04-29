<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VehicleRepository;
use App\Repository\EnergyRepository;
use DateTime;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Areas;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/vehicle', name: 'app_api_vehicle_')]
#[OA\Tag(name: 'Vehicle')]
#[Areas(["default"])]
final class VehicleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly VehicleRepository      $repository,
        private readonly EnergyRepository       $energyRepository,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path:"/api/vehicle/add",
        summary:"Ajout d'un nouveau véhicule",
        requestBody :new RequestBody(
            description: "Données du véhicule à ajouter",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "brand",
                    type: "string",
                    example: "Renault"
                ),
                    new Property(
                        property: "model",
                        type: "string",
                        example: "R4"
                    ),
                    new Property(
                        property: "color",
                        type: "string",
                        example: "Blanche"
                    ),
                    new Property(
                        property: "registration",
                        type: "string",
                        example: "9999 ZZ 75"
                    ),
                    new Property(
                        property: "registrationFirstDate",
                        type: "date",
                        example: "1970-01-01"
                    ),
                    new Property(
                        property: "nbPlace",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "energy",
                        type: "integer",
                        example: 1
                    ),
                ], type: "object"))]
        ),

    )]
    #[OA\Response(
        response: 201,
        description: 'Véhicule ajouté avec succès',
        content: new Model(type: Vehicle::class, groups: ['vehicle_read'])
    )]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        //Pour vérifier la date
        $content = $request->getContent();
        $data = json_decode($content, true);


        $vehicle = $this->serializer->deserialize($request->getContent(), Vehicle::class, 'json');
        $vehicle->setCreatedAt(new DateTimeImmutable());

        //Vérification sur les données
        $checkVehicleRequirements = $this->checkVehicleRequirements($vehicle, $request);
        if ($checkVehicleRequirements['error'] === true)
        {
            return new JsonResponse(['error' => true, 'message' => $checkVehicleRequirements['message'], 'field' => $checkVehicleRequirements['field']], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier et associer l'énergie
        $energy = $this->energyRepository->find($data['energy']);
        if (!$energy) {
            return new JsonResponse(['error' => 'Ce carburant n\'existe pas', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }
        $vehicle->setEnergy($energy);

        // Associer le propriétaire (Owner) à l'utilisateur actuel
        $vehicle->setOwner($user);

        $this->manager->persist($vehicle);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($vehicle, 'json', ['groups' => ['vehicle_read']]);

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);

    }

    #[Route('/list', name: 'showAll', methods: 'GET')]
    #[Areas(["default"])]
    #[OA\Get(
        path:"/api/vehicle/list",
        summary:"Récupérer tous les véhicules du User.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule(s) trouvée(s) avec succès',
        content: new Model(type: Vehicle::class, groups: ['vehicle_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $vehicles = $this->repository->findBy(['owner' => $user->getId()]);

        if ($vehicles) {
            $responseData = $this->serializer->serialize($vehicles, 'json', ['groups' => ['vehicle_read']]);

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    #[Areas(["default"])]
    #[OA\Get(
        path:"/api/vehicle/{id}",
        summary:"Récupérer un véhicule du User avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule trouvé avec succès',
        content: new Model(type: Vehicle::class, groups: ['vehicle_read'])
    )]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($vehicle) {
            $responseData = $this->serializer->serialize($vehicle, 'json', ['groups' => ['vehicle_read']]);

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(['error' => true, 'message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[Areas(["default"])]
    #[OA\Put(
        path:"/api/vehicle/{id}",
        summary:"Modification d'un véhicule du User",
        requestBody :new RequestBody(
            description: "Données du véhicule à modifier.",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "brand",
                    type: "string",
                    example: "Renault"
                ),
                    new Property(
                        property: "model",
                        type: "string",
                        example: "R4"
                    ),
                    new Property(
                        property: "color",
                        type: "string",
                        example: "Blanche"
                    ),
                    new Property(
                        property: "registration",
                        type: "string",
                        example: "9999 ZZ 75"
                    ),
                    new Property(
                        property: "registrationFirstDate",
                        type: "date",
                        example: "1970-01-01"
                    ),
                    new Property(
                        property: "nbPlace",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "energy",
                        type: "integer",
                        example: 1
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule modifié avec succès',
        content: new Model(type: Vehicle::class, groups: ['vehicle_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if (!$vehicle)
        {
            return new JsonResponse(['error' => true, 'message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }
        $vehicle = $this->serializer->deserialize(
            $request->getContent(),
            Vehicle::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $vehicle]
        );

        //Vérification sur les données
        $checkVehicleRequirements = $this->checkVehicleRequirements($vehicle, $request);
        if ($checkVehicleRequirements['error'] === true)
        {
            return new JsonResponse(['error' => true, 'message' => $checkVehicleRequirements['message'], 'field' => $checkVehicleRequirements['field']], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        // Vérifier et associer l'énergie
        $energy = $this->energyRepository->find($data['energy']);
        if (!$energy) {
            return new JsonResponse(['error' => 'Ce carburant n\'existe pas', 'field' => 'energy'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle->setEnergy($energy);

        $vehicle->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();


        $responseData = $this->serializer->serialize($vehicle, 'json', ['groups' => ['vehicle_read']]);

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[Areas(["default"])]
    #[OA\Delete(
        path:"/api/vehicle/{id}",
        summary:"Supprimer un véhicule du User.",
    )]
    #[OA\Response(
        response: 204,
        description: 'Véhicule supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Paramètre non trouvé'
    )]
    public function delete(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($vehicle) {
            $this->manager->remove($vehicle);
            $this->manager->flush();

            return new JsonResponse(['message' => 'Le véhicule a été supprimé avec succès.'], Response::HTTP_OK);
        }

        return new JsonResponse(['error' => true, 'message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

    private function checkVehicleRequirements(Vehicle $vehicle, Request $request): array
    {
        $isError = false;
        $returnMessage = 'ok';
        $returnField = '';

        // Vérifier que le nombre de places est au moins égal à 1.
        if ($vehicle->getNbPlace() < 1) {
            $isError = true;
            $returnMessage = 'Le nombre de places doit être au minimum de 1.';
            $returnField = 'nbPlace';
        }
        //La marque est obligatoire.
        if ($vehicle->getBrand() === null || $vehicle->getBrand() === '') {
            $isError = true;
            $returnMessage = 'La marque est obligatoire.';
            $returnField = 'brand';
        }
        //Le modèle est obligatoire.
        if ($vehicle->getModel() === null || $vehicle->getModel() === '') {
            $isError = true;
            $returnMessage = 'Le modèle est obligatoire.';
            $returnField = 'model';
        }
        //La couleur est obligatoire.
        if ($vehicle->getColor() === null || $vehicle->getColor() === '') {
            $isError = true;
            $returnMessage = 'La couleur est obligatoire.';
            $returnField = 'color';
        }
        //L'immatriculation est obligatoire et doit être unique
        if ($vehicle->getRegistration() === null || $vehicle->getRegistration() === '') {
            $isError = true;
            $returnMessage = 'L\'immatriculation est obligatoire.';
            $returnField = 'registration';
        }
        //La date de première immatriculation est obligatoire et doit être au bon format.
        if ($vehicle->getRegistrationFirstDate() === null ||
            $vehicle->getRegistrationFirstDate() === '' ||
            (!($vehicle->getRegistrationFirstDate() instanceof \DateTime) &&
                !\DateTime::createFromFormat('Y-m-d', $vehicle->getRegistrationFirstDate()))) {
            $isError = true;
            $returnMessage = 'La date d\'immatriculation doit être une date valide au format YYYY-MM-DD.';
            $returnField = 'registrationFirstDate';
        }
        //L'énergie est obligatoire.
        $data = json_decode($request->getContent(), true);
        if (!isset($data['energy']) && $vehicle->getEnergy() === null) {
            $isError = true;
            $returnMessage = 'Il faut choisir une motorisation existante !';
            $returnField = 'energy';
        }


        return ['error' => $isError, 'message' => $returnMessage, 'field' => $returnField];
    }



}
