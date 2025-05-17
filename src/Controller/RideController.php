<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use App\Repository\EcorideRepository;
use App\Service\MongoService;
use App\Service\RideService;
use App\Service\AddressValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Attribute\Areas;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/ride', name: 'app_api_ride_')]
#[OA\Tag(name: 'Ride')]
#[Areas(["default"])]
final class RideController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RideRepository         $repository,
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
        private readonly RideService            $rideService,
        private readonly AddressValidator       $addressValidator,
        private readonly EcorideRepository      $ecorideRepository,
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path:"/api/ride/add",
        summary:"Ajout d'un nouveau covoiturage",
        requestBody :new RequestBody(
            description: "Données du statut du covoiturage.",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "startingAddress",
                    type: "string",
                    example: "nom de rue ville"
                ),
                    new Property(
                        property: "arrivalAddress",
                        type: "string",
                        example: "nom de rue ville"
                    ),
                    new Property(
                        property: "startingAt",
                        type: "datetime",
                        example: "2025-07-01 10:00:00"
                    ),
                    new Property(
                        property: "arrivalAt",
                        type: "datetime",
                        example: "2025-07-01 12:00:00"
                    ),
                    new Property(
                        property: "price",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "nbPlacesAvailable",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "vehicle",
                        type: "integer",
                        example: 3
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Covoiturage ajouté avec succès',
        content: new Model(type: Ride::class, groups: ['trip_read'])
    )]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        // Récupération des données de la requête
        $data = json_decode($request->getContent(), true);

        //Vérification de la cohérence des données reçues
        $validateConsistentData = $this->rideService->validateConsistentData($data, $user);

        if (isset($validateConsistentData['error'])) {
            return new JsonResponse(
                ["message" => $validateConsistentData['error']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $ride = $this->serializer->deserialize($request->getContent(), Ride::class, 'json');

        $startingAddressValidation = $this->addressValidator->validateAndDecomposeAddress($ride->getStartingAddress());
        if (isset($startingAddressValidation['error'])) {
            return new JsonResponse(['error' => 'startingAddress: ' . $startingAddressValidation['error']], Response::HTTP_BAD_REQUEST);
        }
        $ride->setStartingAddress(json_encode($startingAddressValidation, true));

        $arrivalAddressValidation = $this->addressValidator->validateAndDecomposeAddress($ride->getArrivalAddress());
        if (isset($arrivalAddressValidation['error'])) {
            return new JsonResponse(['error' => 'arrivalAddress: ' . $arrivalAddressValidation['error']], Response::HTTP_BAD_REQUEST);
        }
        $ride->setArrivalAddress(json_encode($arrivalAddressValidation, true));

        // Récupération du véhicule
        $vehicle = $this->manager->getRepository(Vehicle::class)->findOneBy(['id' => $data['vehicle'], 'owner' => $user->getId()]);
        $ride->setVehicle($vehicle);


        // Vérification des champs requis
        $requiredFields = ['startingAddress', 'arrivalAddress', 'startingAt', 'arrivalAt', 'price', 'nbPlacesAvailable', 'vehicle'];

        foreach ($requiredFields as $field) {
            if (empty($ride->{'get' . ucfirst($field)}())) {
                throw new InvalidArgumentException("Le champ '$field' est requis.");
            }
        }

        //Statut par défaut :
        $ride->setStatus(RideStatus::getDefaultStatus());
        // Définir les propriétés gérées par le serveur
        $ride->setDriver($user);
        $ride->setCreatedAt(new DateTimeImmutable());

        // Persistance
        $this->manager->persist($ride);
        $this->manager->flush();

        // Ajouter les préférences sérialisées dans MongoDB
        $this->mongoService->add([
            'rideId' => $ride->getId(),
            'startingAddress' => $ride->getStartingAddress(),
            'arrivalAddress' => $ride->getArrivalAddress(),
            'startingAt' => $ride->getStartingAt(),
            'arrivalAt' => $ride->getArrivalAt(),
            'duration' => ($ride->getArrivalAt()->diff($ride->getStartingAt())->h * 60) + $ride->getArrivalAt()->diff($ride->getStartingAt())->i,
            'price' => $ride->getPrice(),
            'nbPlacesAvailable' => $ride->getNbPlacesAvailable(),
            // Données utilisateur
            'driver' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'grade' => $user->getGrade(),
            ],
            // Données véhicule
            'vehicle' => [
                'brand' => $ride->getVehicle()->getBrand(),
                'model' => $ride->getVehicle()->getModel(),
                'color' => $ride->getVehicle()->getColor(),
                // Vérifier le type avant d'accéder aux propriétés
                'energy' => $ride->getVehicle()->getEnergy(),
                'isEco' => is_object($ride->getVehicle()->getEnergy())
                    ? $ride->getVehicle()->getEnergy()->name === 'ECO'
                    : $ride->getVehicle()->getEnergy() === 'ECO',
                ],
        ]);


        // Réponse
        return new JsonResponse(
            ["message" => "Covoiturage ajouté avec succès"],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/{state}', name: 'showAllOwner', methods: 'GET')]
    #[OA\Get(
        path:"/api/ride/list/{state}",
        summary:"Liste les covoiturages du User selon leur état.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturages trouvés avec succès',
        content: new Model(type: Ride::class, groups: ['ride_read'])
    )]
    public function showAllOwner(#[CurrentUser] ?User $user, Request $request, string $state): JsonResponse
    {
        //Récupération du numéro de page et la limite par page
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);

        //requête queryBuilder
        $queryBuilder = $this->repository->createQueryBuilder('r')
            ->where('r.driver = :driver')
            ->setParameter('driver', $user)
            ->orderBy('r.createdAt', 'DESC');

        //Selon le paramètre, on adapte le WHERE
        if ($state !== 'all') {
            $queryBuilder
                ->andWhere('r.status = :status')
                ->setParameter('status', $state);
        }

        // Compter le nombre total d'éléments
        $countQuery = clone $queryBuilder;
        $totalItems = $countQuery->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Ajouter la pagination
        $queryBuilder->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $rides = $queryBuilder->getQuery()->getResult();

        if (count($rides) > 0) {
            $responseData = $this->serializer->serialize(
                [
                    'rides' => $rides,
                    'pagination' => [
                        'page_courante' => $page,
                        'pages_totales' => ceil($totalItems / $limit),
                        'elements_totaux' => $totalItems,
                        'elements_par_page' => $limit
                    ]
                ],
                'json',
                ['groups' => ['ride_read']]
            );

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(['message' => 'Il n\'y a pas de covoiturage dans cet état pour cet utilisateur.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    #[OA\Get(
        path:"/api/ride/{id}",
        summary:"Récupérer un covoiturage du User avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage trouvé avec succès',
        content: new Model(type: Ride::class, groups: ['trip_read'])
    )]
    public function showByIdToOwner(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $trip = $this->repository->findOneBy(['id' => $id, 'driver' => $user->getId()]);

        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        $responseData = $this->serializer->serialize(
            $trip,
            'json',
            ['groups' => ['ride_read']]
        );
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    /**
     * @throws Exception|TransportExceptionInterface
     */
    #[Route('/{id}/update', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/{id}/update",
        summary:"Modification d'un covoiturage non démarré, un mail est envoyé à tous les passagers",
        requestBody :new RequestBody(
            description: "Données du statut du covoiturage.",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                        property: "startingAt",
                        type: "datetime",
                        example: "2025-07-01 10:00:00"
                    ),
                    new Property(
                        property: "arrivalAt",
                        type: "datetime",
                        example: "2025-07-01 12:00:00"
                    ),
                    new Property(
                        property: "price",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "nbPlacesAvailable",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "vehicle",
                        type: "integer",
                        example: 3
                    ),
                ], type: "object"))]
        ),

    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage modifié avec succès.',
        content: new Model(type: Ride::class, groups: ['ride_read'])
    )]
    #[OA\Response(
        response: 403,
        description: 'Covoiturage non modifiable.'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé.'
    )]
    public function edit(#[CurrentUser] ?User $user, Request $request, int $id): JsonResponse
    {
        $ride = $this->repository->findOneBy(['id' => $id, 'driver' => $user->getId()]);
        // Vérification de l'existence du covoiturage
        if (!$ride) {
            return new JsonResponse(['message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        // Vérification du statut du covoiturage pour savoir si celui-ci permet la modification
        $possibleActions = RideStatus::getPossibleActions();
        if (!array_key_exists('update', $possibleActions) ||
            !in_array(strtolower($ride->getStatus()), $possibleActions['update']['initial'])) {
            return new JsonResponse(
                ['message' => 'Le covoiturage ne peut pas être modifié en l\'état, il ne doit pas être démarré.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        // Vérification si les données sont identiques
        if ($this->rideService->isRideDataIdentical($ride, $dataRequest)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        // Liste des champs modifiables
        $champsModifiables = [
            "vehicle", "startingAddress", "arrivalAddress",
            "startingAt", "arrivalAt", "price", "nbPlacesAvailable"
        ];

        // Récupération des passagers
        $passengers = $ride->getPassenger();
        $passengerCount = $passengers->count();

        //on supprime de $dataRequest les champs inchangés de $ride


        // Validation des données de la requête
        $validationResult = $this->rideService->validateRideUpdateData(
            $ride,
            $dataRequest,
            $champsModifiables,
            $passengerCount,
            $user
        );
        if ($validationResult !== true) {
            return new JsonResponse(['message' => $validationResult], Response::HTTP_BAD_REQUEST);
        }

        // Mise à jour de l'entité
        $this->rideService->updateRideEntity($ride, $dataRequest);

        // Notification des passagers si nécessaire
        if ($passengerCount > 0) {
            $this->rideService->notifyPassengersAboutRideUpdate($ride, $passengers);
        }

        // Mise à jour dans MongoDB
        $this->rideService->updateRideInMongo($ride);

        return new JsonResponse(['message' => 'Covoiturage modifié avec succès'], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path:"/api/ride/{id}",
        summary:"Supprimer un covoiturage.",
    )]
    #[OA\Response(
        response: 204,
        description: 'Covoiturage supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé'
    )]
    public function delete(Ride $ride): JsonResponse // Injection directe de l'entité
    {
        //si pas de passager
        if ($ride->getPassenger()->count() > 0) {
            return new JsonResponse(['message' => 'Ce covoiturage ne peut pas être supprimé car il y a des passagers inscrits. Vous pouvez par contre l\'annuler'], Response::HTTP_FORBIDDEN);
        }
        //Suppression de MongoDB
        $this->mongoService->delete($ride->getId());

        $this->manager->remove($ride);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

}
