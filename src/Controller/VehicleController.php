<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\EnergyEnum;
use App\Service\VehicleService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VehicleRepository;
use DateTimeImmutable;
use Exception;
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
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
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
        private readonly SerializerInterface    $serializer,
        private readonly VehicleService         $vehicleService,
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path: "/api/vehicle/add",
        summary: "Ajout d'un nouveau véhicule",
        requestBody: new RequestBody(
            description: "Données du véhicule à ajouter",
            required: true,
            content: [new MediaType(mediaType: "application/json",
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
                        property: "licensePlate",
                        type: "string",
                        example: "9999 ZZ 75"
                    ),
                    new Property(
                        property: "licenseFirstDate",
                        type: "date",
                        example: "1970-01-01"
                    ),
                    new Property(
                        property: "maxNbPlacesAvailable",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "energy",
                        description: "Type d'énergie du véhicule",
                        type: "string",
                        example: "Électrique, Hybride ou Carburant inflammable"
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Véhicule ajouté avec succès'
    )]
    #[OA\Response(
        response: 400,
        description: 'Données invalides'
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur sémantique dans les données (ex: énergie non trouvée)'
    )]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $rawContent = $request->getContent();

        try {
            // Décoder le contenu JSON une seule fois
            $jsonData = json_decode($rawContent, true);

            //Désérialiser l'objet Vehicle.
            $vehicle = $this->serializer->deserialize(
                $rawContent,
                Vehicle::class,
                'json'
            );

            //Vérification sur les données
            $checkVehicleRequirements = $this->checkVehicleRequirements($vehicle, $request);
            if ($checkVehicleRequirements['error'] === true) {
                return new JsonResponse(
                    [
                        'error' => true,
                        'message' => $checkVehicleRequirements['message'],
                        'field' => $checkVehicleRequirements['field']
                    ], Response::HTTP_BAD_REQUEST);
            }
        } catch (NotEncodableValueException $e) {
            return new JsonResponse(['error' => true, 'message' => 'JSON malformé: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        // Récupération et validation de l'énergie
        $energyName = $jsonData['energy'];
        $energyReturn = $this->validateEnergy($energyName);
        if ($energyReturn instanceof JsonResponse) {
            return $energyReturn;
        }
        $vehicle->setEnergy($energyReturn);

        $vehicle->setLicenseFirstDate(new DateTime($jsonData['licenseFirstDate']));
        $vehicle->setMaxNbPlacesAvailable($jsonData['maxNbPlacesAvailable']);

        // Définir les propriétés gérées par le serveur
        $vehicle->setOwner($user);
        $vehicle->setCreatedAt(new DateTimeImmutable());

        // Persistance
        $this->manager->persist($vehicle);
        $this->manager->flush();

        // Réponse
        return new JsonResponse(
            ["message" => "Véhicule ajouté avec succès"],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list', name: 'showAll', methods: 'GET')]
    #[OA\Get(
        path: "/api/vehicle/list",
        summary: "Récupérer tous les véhicules du User.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule(s) trouvée(s) avec succès',
        content: new Model(type: Vehicle::class, groups: ['user_account'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Aucun véhicule trouvé'
    )]
    public function showAll(#[CurrentUser] ?User $user): JsonResponse
    {
        $vehicles = $this->repository->findBy(['owner' => $user->getId()]);

        // Convertir les codes d'énergie en valeurs descriptives pour chaque véhicule
        foreach ($vehicles as $vehicle) {
            $this->vehicleService->convertEnergyCodeToValue($vehicle);
        }

        return $this->json($vehicles, Response::HTTP_OK, [], ['groups' => 'user_account']);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    #[OA\Get(
        path: "/api/vehicle/{id}",
        summary: "Récupérer un véhicule du User avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule trouvé avec succès',
        content: new Model(type: Vehicle::class, groups: ['user_account'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicles = $this->repository->findBy(['id' => $id, 'owner' => $user->getId()]);

        if ($vehicles) {
            // Convertir les codes d'énergie en valeurs descriptives pour chaque véhicule
            foreach ($vehicles as $vehicle) {
                $this->vehicleService->convertEnergyCodeToValue($vehicle);
            }

            $responseData = $this->serializer->serialize(
                $vehicles,
                'json',
                ['groups' => ['user_account']]
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(['message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @throws Exception
     */
    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: "/api/vehicle/{id}",
        summary: "Modification d'un véhicule du User",
        requestBody: new RequestBody(
            description: "Données du véhicule à modifier.",
            required: true,
            content: [new MediaType(mediaType: "application/json",
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
                        property: "licensePlate",
                        type: "string",
                        example: "9999 ZZ 75"
                    ),
                    new Property(
                        property: "licenseFirstDate",
                        type: "date",
                        example: "1970-01-01"
                    ),
                    new Property(
                        property: "maxNbPlacesAvailable",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "energy",
                        description: "Type d'énergie du véhicule",
                        type: "string",
                        example: "Électrique, Hybride ou Carburant inflammable"
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Véhicule modifié avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if (!$vehicle) {
            return new JsonResponse(['message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        $jsonData = json_decode($request->getContent(), true);

        $vehicle = $this->serializer->deserialize(
            $request->getContent(),
            Vehicle::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $vehicle,
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['owner']
            ]
        );

        //Vérification sur les données
        $checkVehicleRequirements = $this->checkVehicleRequirements($vehicle, $request);
        if ($checkVehicleRequirements['error'] === true) {
            return new JsonResponse(
                [
                    'error' => true,
                    'message' => $checkVehicleRequirements['message'],
                    'field' => $checkVehicleRequirements['field']
                ], Response::HTTP_BAD_REQUEST);
        }

        // Récupération et validation de l'énergie
        $energyName = $jsonData['energy'];
        $energyReturn = $this->validateEnergy($energyName);
        if ($energyReturn instanceof JsonResponse) {
            return $energyReturn;
        }
        $vehicle->setEnergy($energyReturn);

        $vehicle->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        // Convertir le code d'énergie en valeur descriptive pour l'affichage
        $this->vehicleService->convertEnergyCodeToValue($vehicle);

        $responseData = $this->serializer->serialize($vehicle, 'json', ['groups' => ['user_account']]);
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: "/api/vehicle/{id}",
        summary: "Supprimer un véhicule du User.",
    )]
    #[OA\Response(
        response: 204,
        description: 'Véhicule supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Véhicule non trouvé'
    )]
    public function delete(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $vehicle = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        if ($vehicle) {
            $this->manager->remove($vehicle);
            $this->manager->flush();

            return new JsonResponse(['success' => true], Response::HTTP_OK);
        }

        return new JsonResponse(['message' => 'Ce véhicule n\'existe pas.'], Response::HTTP_NOT_FOUND);
    }

    /**
     * Vérifie les exigences pour un véhicule valide
     */
    private function checkVehicleRequirements(Vehicle $vehicle, Request $request): array
    {
        $isError = false;
        $returnMessage = 'ok';
        $returnField = '';

        // Vérifier que le nombre de places est au moins égal à 1.
        if ($vehicle->getMaxNbPlacesAvailable() < 1) {
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
        if ($vehicle->getLicensePlate() === null || $vehicle->getLicensePlate() === '') {
            $isError = true;
            $returnMessage = 'L\'immatriculation est obligatoire.';
            $returnField = 'licensePlate';
        }
        //La date de première immatriculation est obligatoire et doit être au bon format.
        if ($vehicle->getLicenseFirstDate() === null ||
            $vehicle->getLicenseFirstDate() === '' ||
            (!($vehicle->getLicenseFirstDate() instanceof DateTime) &&
                !DateTime::createFromFormat('Y-m-d', $vehicle->getLicenseFirstDate()))) {
            $isError = true;
            $returnMessage = 'La date d\'immatriculation doit être une date valide au format YYYY-MM-DD.';
            $returnField = 'licenseFirstDate';
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

    /**
     * Valide le type d'énergie et retourne le code correspondant ou une erreur
     *
     * @param string $energyValue La valeur d'énergie à valider (ex : "Électrique")
     * @return string|JsonResponse Le code d'énergie ou une réponse d'erreur
     */
    private function validateEnergy(string $energyValue): string|JsonResponse
    {
        // Récupère tous les noms des cas de l'énumération
        $validEnergies = [];
        foreach (EnergyEnum::cases() as $case) {
            $validEnergies[$case->value] = $case->name;
        }

        // Vérifie si la valeur fournie existe dans les valeurs de l'énumération
        if (!array_key_exists($energyValue, $validEnergies)) {
            return new JsonResponse(
                ['error' => true, 'message' => 'Type d\'énergie non reconnue: ' . $energyValue],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $validEnergies[$energyValue];
    }

}
