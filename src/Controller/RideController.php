<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use App\Service\MongoService;
use App\Service\RideService;
use App\Service\AddressValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
    )
    {
    }

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
                    type: "json",
                    example: "{\"street\": \"nom de la rue\", \"postcode\": \"75000\", \"city\": \"ville\"}"
                ),
                    new Property(
                        property: "arrivalAddress",
                        type: "json",
                        example: "{\"street\": \"nom de la rue\", \"postcode\": \"75000\", \"city\": \"ville\"}"
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
        content: new Model(type: Ride::class, groups: ['ride_read'])
    )]
    #[IsGranted('ROLE_USER')]
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

        $startingAddressValidation = $this->addressValidator->validateAndDecomposeAddress($data['startingAddress']);
        if (isset($startingAddressValidation['error'])) {
            return new JsonResponse(['error' => 'startingAddress: ' . $startingAddressValidation['error']], Response::HTTP_BAD_REQUEST);
        }
        $ride->setStartingStreet($startingAddressValidation['street']);
        $ride->setStartingPostCode($startingAddressValidation['postcode']);
        $ride->setStartingCity($startingAddressValidation['city']);

        $arrivalAddressValidation = $this->addressValidator->validateAndDecomposeAddress($data['arrivalAddress']);
        if (isset($arrivalAddressValidation['error'])) {
            return new JsonResponse(['error' => 'arrivalAddress: ' . $arrivalAddressValidation['error']], Response::HTTP_BAD_REQUEST);
        }
        $ride->setArrivalStreet($arrivalAddressValidation['street']);
        $ride->setArrivalPostCode($arrivalAddressValidation['postcode']);
        $ride->setArrivalCity($arrivalAddressValidation['city']);

        // Récupération du véhicule
        $vehicle = $this->manager->getRepository(Vehicle::class)->findOneBy(['id' => $data['vehicle'], 'owner' => $user->getId()]);
        $ride->setVehicle($vehicle);


        // Vérification des autres champs requis
        $requiredFields = ['startingAt', 'arrivalAt', 'price', 'nbPlacesAvailable'];

        foreach ($requiredFields as $field) {
            if (empty($ride->{'get' . ucfirst($field)}())) {
                return new JsonResponse(
                    ["message" => "Le champ '$field' est requis."],
                    Response::HTTP_BAD_REQUEST
                );
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

        // Réponse
        return new JsonResponse(
            ["message" => "Covoiturage ajouté avec succès"],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/{state}', name: 'showAll', methods: 'GET')]
    #[OA\Get(
        path:"/api/ride/list/{state}",
        summary:"Liste les covoiturages du User selon leur état.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturages trouvés avec succès',
        content: new Model(type: Ride::class, groups: ['ride_read'])
    )]
    #[IsGranted('ROLE_USER')]
    public function showAll(#[CurrentUser] ?User $user, Request $request, string $state): JsonResponse
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

    #[Route('/show/{id}', name: 'show', methods: 'GET')]
    #[OA\Get(
        path:"/api/ride/show/{id}",
        summary:"Récupérer un covoiturage avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage trouvé avec succès',
        content: new Model(type: Ride::class, groups: ['ride_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage n\'existe pas'
    )]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $ride = $this->repository->findOneBy(['id' => $id]);

        if (!$ride) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        if ($user) {
            $responseData = $this->serializer->serialize(
                $ride,
                'json',
                ['groups' => ['ride_read', 'ride_detail']]
            );
        } else {
            $responseData = $this->serializer->serialize(
                $ride,
                'json',
                ['groups' => ['ride_read']]
            );
        }

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    /**
     * @throws Exception|TransportExceptionInterface
     */
    #[Route('/update/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/update/{id}",
        summary:"Modification d'un covoiturage non démarré, un mail est envoyé à tous les passagers",
        requestBody :new RequestBody(
            description: "Données du statut du covoiturage.",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
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
    #[IsGranted('ROLE_USER')]
    public function edit(#[CurrentUser] ?User $user, Request $request, int $id): JsonResponse
    {
        //Récupération de l'entité à modifier
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

        //Récupération des données de la requête
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        // Liste des champs modifiables
        $champsModifiables = RideStatus::getRideFieldsUpdatable();

        $dataRequestValidated = $dataRequest;
        //s'il y a des champs non modifiables, on indique les champs en trop et on retourne
        foreach ($dataRequestValidated as $key => $value) {
            if (!in_array($key, $champsModifiables)) {
                return new JsonResponse(['message' => "Le champ $key n'est pas modifiable"], Response::HTTP_BAD_REQUEST);
            }
            //On supprime de $dataRequestValidated les champs inchangés pour éviter d'envoyer un mail si aucun changement n'est effectué
            if ($ride->{'get' . ucfirst($key)}() === $value) {
                unset($dataRequestValidated[$key]);
            }
            //Dans le cas où $ride->{'get' . ucfirst($key)}() est un objet entité
            if (is_object($ride->{'get' . ucfirst($key)}())) {
                if ($ride->{'get' . ucfirst($key)}()->getId() === $value) {
                    unset($dataRequestValidated[$key]);
                }
            }
        }
        //On vérifie que $dataRequestValidated n'est pas un tableau vide
        if (empty($dataRequestValidated)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }


        // Récupération des passagers
        $passengers = $ride->getPassenger();
        $passengerCount = $passengers->count();
        $notifyPassengersAboutRideUpdate = false;

        //Si le véhicule est à changer, on vérifie qu'il existe et qu'il appartient au user
        if (isset($dataRequestValidated['vehicle'])) {
            $vehicle = $this->manager->getRepository(Vehicle::class)->findOneBy(['id' => $dataRequestValidated['vehicle'], 'owner' => $user->getId()]);
            if (!$vehicle)
            {
                return new JsonResponse(["message" => "Le véhicule n'existe pas ou n'appartient pas à l'utilisateur"], Response::HTTP_BAD_REQUEST);
            }
            $ride->setVehicle($vehicle);
            //S'il y a des passagers, on les notifiera une fois flush
            if ($passengerCount > 0 ) { $notifyPassengersAboutRideUpdate = true; }
        }

        // Vérification du nombre de places
        if (isset($dataRequest['nbPlacesAvailable'])) {
            //Si le nombre de places à mettre à jour est <= 0.
            if ($dataRequest['nbPlacesAvailable'] <= 0) {
                return new JsonResponse(["message" => "Vous ne pouvez pas mettre 0 place disponible. Annulez ou supprimez le covoiturage."], Response::HTTP_BAD_REQUEST);
            }
            //Si on veut mettre plus de place disponible que de place dans la voiture
            if ($dataRequest['nbPlacesAvailable'] > $ride->getVehicle()->getMaxNbPlacesAvailable()) {
                return new JsonResponse(["message" => "Il n'y a pas assez de place dans la voiture pour accueillir autant de monde."], Response::HTTP_BAD_REQUEST);
            }
            //Si on veut mettre moins de place disponible que de passager déjà inscrit
            if ($passengerCount > $dataRequest['nbPlacesAvailable']) {
                return new JsonResponse(["message" => "Vous ne pouvez pas mettre moins de places que de participants déjà inscrits"], Response::HTTP_BAD_REQUEST);
            }
            $ride->setNbPlacesAvailable($dataRequest['nbPlacesAvailable']);
        }
        //S'il y a des passagers, on ne peut pas modifier le tarif
        if (isset($dataRequest['price'])) {
            if ($passengerCount > 0) {
                return new JsonResponse(["message" => "Le prix ne peut pas être modifié lorsqu'il y a au moins un passager inscrit"], Response::HTTP_BAD_REQUEST);
            }
            $ride->setPrice($dataRequest['price']);
        }


        // Mise à jour de l'entité
        $ride->setUpdatedAt(new DateTimeImmutable());

        $this->manager->persist($ride);
        $this->manager->flush();


        // Mise à jour dans MongoDB
        $this->rideService->updateRideInMongo($ride);

        // Notification des passagers si le véhicule est changé
        if ($passengerCount > 0 && $notifyPassengersAboutRideUpdate) {
            $this->rideService->notifyPassengersAboutRideUpdate($ride, $passengers);
        }

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
    #[IsGranted('ROLE_USER')]
    public function delete(Ride $ride): JsonResponse
    {
        //si des passagers inscrits, on ne peut pas le supprimer
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
