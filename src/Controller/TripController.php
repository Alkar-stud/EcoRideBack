<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\TripRepository;
use App\Service\TripMongoService;
use App\Service\TripService;
use App\Service\MailService;
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
use ReflectionMethod;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/trip', name: 'app_api_trip_')]
#[OA\Tag(name: 'Trip')]
#[Areas(["default"])]
final class TripController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly TripRepository          $repository,
        private readonly SerializerInterface     $serializer,
        private readonly TripService             $tripService,
        private readonly TripMongoService        $tripMongoService,
        private readonly MailService             $mailService,
    )
    {
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path:"/api/trip/add",
        summary:"Ajout d'un nouveau covoiturage",
        requestBody :new RequestBody(
            description: "Données du statut du covoiturage. duration est le temps de trajet en minutes",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "startingAddress",
                    type: "string",
                    example: "rue|VILLE"
                ),
                    new Property(
                        property: "arrivalAddress",
                        type: "string",
                        example: "rue|VILLE"
                    ),
                    new Property(
                        property: "startingAt",
                        type: "datetime",
                        example: "2025-07-01 10:00:00"
                    ),
                    new Property(
                        property: "duration",
                        type: "integer",
                        example: 120
                    ),
                    new Property(
                        property: "nbCredit",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "nbPlaceRemaining",
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
        content: new Model(type: Trip::class, groups: ['trip_read'])
    )]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $trip = $this->serializer->deserialize($request->getContent(), Trip::class, 'json');
        //Récupération des autres données
        $data = json_decode($request->getContent(), true);
        //Vérification si le champ 'vehicle' existe
        if (!isset($data['vehicle']))
        {
            return new JsonResponse('Un véhicule vous appartenant est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        //ajout Vehicle à Trip
        if ($this->tripService->getTripVehicle($data['vehicle'], $user) === null)
        {
            return new JsonResponse('Un véhicule vous appartenant est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        $trip->setVehicle($this->tripService->getTripVehicle($data['vehicle'], $user));
        //ajout Driver = CurrentUser à Trip
        $trip->setDriver($user);
        //ajout Status correspondant au statut par défaut
        $trip->setStatus($this->tripService->getDefaultStatus());
        //Ajout durée du voyage
        if (!isset($data['duration'])) {
            return new JsonResponse('La durée du voyage est obligatoire', Response::HTTP_BAD_REQUEST);
        }
        $trip->setDuration($data['duration']);

        //explode et json_encode des adresses
        $startingAddress = explode('|', $trip->getStartingAddress());
        $arrivalAddress = explode('|', $trip->getArrivalAddress());
        //mise en majuscule des villes
        $startingAddress[1] = mb_convert_case($startingAddress[1], MB_CASE_UPPER, "UTF-8");
        $arrivalAddress[1] = mb_convert_case($arrivalAddress[1], MB_CASE_UPPER, "UTF-8");
        //Update dans $trip
        $trip->setStartingAddress(json_encode($startingAddress, true));
        $trip->setArrivalAddress(json_encode($arrivalAddress, true));

        $trip->setCreatedAt(new DateTimeImmutable());
        $this->manager->persist($trip);
        $this->manager->flush();

        // Ajouter les préférences sérialisées dans MongoDB
        $this->tripMongoService->add([
            'id_covoiturage' => $trip->getId(),
            'startingAddress' => $trip->getStartingAddress(),
            'arrivalAddress' => $trip->getArrivalAddress(),
            'startingAt' => $trip->getStartingAt(),
            'duration' => $trip->getDuration(),
            'nbCredit' => $trip->getNbCredit(),
            'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
            'nbParticipant' => 0,
            // Données utilisateur
            'driver' => [
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'grade' => $user->getGrade(),
            ],
            // Données véhicule
            'vehicle' => [
                'brand' => $trip->getVehicle()->getBrand(),
                'model' => $trip->getVehicle()->getModel(),
                'color' => $trip->getVehicle()->getColor(),
                'energy' => $trip->getVehicle()->getEnergy()?->getLibelle(),
                'isEco' => $trip->getVehicle()->getEnergy()?->isEco(),
            ],
        ]);

        return new JsonResponse(
            [
                'id'  => $trip->getId(),
                'status'  => [
                    'libelle' => $trip->getStatus()?->getLibelle()
                ],
                'startingAddress'  => $trip->getStartingAddress(),
                'arrivalAddress'  => $trip->getArrivalAddress(),
                'startingAt' => $trip->getStartingAt(),
                'tripDuration' => $trip->getDuration(),
                'nbCredit' => $trip->getNbCredit(),
                'nbPlace' => $trip->getNbPlaceRemaining(),
                'createdAt' => $trip->getCreatedAt()
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/list/{state}', name: 'showAllOwner', methods: 'GET')]
    #[OA\Get(
        path:"/api/trip/list/{state}",
        summary:"Liste les covoiturages du User selon leur état.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturages trouvés avec succès',
        content: new Model(type: Trip::class, groups: ['trip_read'])
    )]
    public function showAllOwner(#[CurrentUser] ?User $user, Request $request, string $state): JsonResponse
    {
        $possibleCodeStatus = $this->tripService->getPossibleStatus();
        // Pagination pour les covoiturages
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        //Vérification si l'état demandé existe
        if (!array_key_exists($state, $possibleCodeStatus) && $state !== 'all')
        {
            return new JsonResponse(['error' => true, 'message' => 'Cet état n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }


        //Si l'état demandé est 'all' pour tout afficher
        if ($state === 'all')
        {
            $trips = $this->repository->findBy(
                ['driver' => $user->getId()],
                ['startingAt' => 'ASC'],
                $limit,
                ($page - 1) * $limit
            );
        }
        else
        {
            $trips = $this->repository->findBy(
                ['driver' => $user->getId(), 'status' => $possibleCodeStatus[$state]],
                ['startingAt' => 'ASC'],
                $limit,
                ($page - 1) * $limit
            );
        }

        if ($trips) {
            $responseData = $this->serializer->serialize(
                $trips,
                'json',
                ['groups' => ['trip_read', 'trip_detail']]
            );
            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse(['message' => 'Il n\'y a pas de covoiturage dans cet état pour cet utilisateur.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/guest/{id}', name: 'show_guest', methods: 'GET')]
    #[OA\Get(
        path:"/api/trip/guest/{id}",
        summary:"Récupérer un covoiturage avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage trouvé avec succès',
        content: new Model(type: Trip::class, groups: ['trip_read', 'trip_detail'])
    )]
    public function showByIdToGuest(int $id): JsonResponse
    {
        //Pour le public, il faut aller chercher dans mongoDB
        $trip = $this->tripMongoService->findById($id);

        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(
            [
                'id' => $id,
                'startingAddress' => $trip['startingAddress'],
                'arrivalAddress' => $trip['arrivalAddress'],
                'startingAt' => $trip['startingAt'],
                'duration' => $trip['duration'],
                'nbCredit' => $trip['nbCredit'],
                'nbPlaceRemaining' => $trip['nbPlaceRemaining'],
                'nbParticipant' => $trip['nbParticipant'],
                'driver' => [
                    'pseudo' => $trip['driver']['pseudo'],
                    'photo' => $trip['driver']['photo'],
                    'grade' => $trip['driver']['grade']
                ],
                'vehicle' => [
                    'brand' => $trip['vehicle']['brand'],
                    'model' => $trip['vehicle']['model'],
                    'color' => $trip['vehicle']['color'],
                    'energy' => [
                        'energy' => $trip['vehicle']['energy'],
                        'isEco' => $trip['vehicle']['isEco']
                    ]
                ],

            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    #[OA\Get(
        path:"/api/trip/{id}",
        summary:"Récupérer un covoiturage du User avec son ID.",
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage trouvé avec succès',
        content: new Model(type: Trip::class, groups: ['trip_read'])
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
            ['groups' => ['trip_read']]
        );
        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/edit/{id}/update', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/trip/edit/{id}/update",
        summary:"Modification d'un covoiturage",
        requestBody :new RequestBody(
            description: "Données du statut du covoiturage. duration est le temps de trajet en minutes",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "startingAddress",
                    type: "string",
                    example: "rue|VILLE"
                ),
                    new Property(
                        property: "arrivalAddress",
                        type: "string",
                        example: "rue|VILLE"
                    ),
                    new Property(
                        property: "startingAt",
                        type: "datetime",
                        example: "2025-07-01 10:00:00"
                    ),
                    new Property(
                        property: "duration",
                        type: "integer",
                        example: 120
                    ),
                    new Property(
                        property: "nbCredit",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "nbPlaceRemaining",
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
        content: new Model(type: Trip::class, groups: ['trip_read'])
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
        $trip = $this->repository->findOneBy(['id' => $id, 'driver' => $user->getId()]);
        //Si le covoiturage n'existe pas
        if (!$trip) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        //Seul l'update des datas sera traité ici, possible en fonction du statut du covoiturage, donc le statut sera modifié ailleurs.
        //Les autres dates seront ajoutées/modifiées aux changements de statut
        // Owner n'est pas modifiable non plus
        $possibleActions = $this->tripService->getPossibleActions();
        //Vérification si l'action 'update' existe, et si elle est possible en fonction du statut du covoiturage
        if (!array_key_exists('update', $possibleActions) || !in_array($trip->getStatus()?->getCode(), $possibleActions['update']['initial']))
        {
            return new JsonResponse(['error' => true, 'message' => 'Le covoiturage ne peut pas être modifié en l\'état.'], Response::HTTP_FORBIDDEN);
        }

        $originalVehicleId = $trip->getVehicle()->getId();
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        //Récupérer les participants (user) du voyage
        $users = $trip->getUser()->map(function ($user) {
            return [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'email' => $user->getEmail(),
            ];
        })->toArray();

        //Modification impossible si des participants sont inscrits, sauf si on augmente le nombre de places
        /* Si participants == 0 → on peut modifier (presque) tout
         * Si participants > 0 →
         * → Si nbPlaceRemaining >= count($users) => on peut modifier ce champ et vehicle.
         */
        $champsModifiables = [
            0=>"vehicle",
            1=>"startingAddress",
            2=>"arrivalAddress",
            3=>"startingAt",
            4=>"duration",
            5=>"nbCredit",
            6=>"nbPlaceRemaining"
        ];
        // Suppression des champs non modifiables
        $dataRequest = array_filter(
            $dataRequest,
            fn($key) => in_array($key, $champsModifiables, true),
            ARRAY_FILTER_USE_KEY
        );

        //S'il y a plus de participants que de places à renseigner et qu'on veut modifier le nombre de places restantes, on ne peut rien modifier.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > $dataRequest['nbPlaceRemaining'])
        {
            $returnMessage = [
                "error" => true,
                "message" => "Vous ne pouvez pas mettre moins de places que de participants",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }
        //S'il y a plus ou égal nbPlaceRemaining que de participants, on ne peut que modifier le nb de place et le véhicule.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) <= $dataRequest['nbPlaceRemaining'] && count($users) > 0)
        {
            unset($champsModifiables[1], $champsModifiables[2], $champsModifiables[3], $champsModifiables[4], $champsModifiables[5]);
        }
        //si changement du nombre de places restantes, mais inférieur au nombre de participants, on ne peut rien modifier.
        if (array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > $dataRequest['nbPlaceRemaining'])
        {
            $returnMessage = [
                "error" => true,
                "message" => "Il y a plus de participant que le nombre de places disponibles demandées",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }
        //Si pas de changement de places restantes à changer, mais au moins 1 participant, on ne peut rien modifier
        if (!array_key_exists('nbPlaceRemaining', $dataRequest) && count($users) > 0)
        {
            $returnMessage = [
                "error" => true,
                "message" => "Vous ne pouvez pas modifier ce covoiturage lorsqu'il y a des participants",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }

        //Mise à jour des champs
        foreach ($dataRequest as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($trip, $setter)) {
                if ($key === 'startingAt') {
                    try {
                        $value = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
                        if (!$value) {
                            throw new InvalidArgumentException('Le format de la date est invalide. Utilisez "Y-m-d H:i:s".');
                        }
                    } catch (Exception $e) {
                        return new JsonResponse(['error' => true, 'message' => 'Erreur lors de la conversion de la date : ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
                    }
                } elseif ($key === 'vehicle') {
                    $vehicle = $this->manager->getRepository(Vehicle::class)->find($value);
                    if (!$vehicle) {
                        return new JsonResponse(['error' => true, 'message' => 'Le véhicule spécifié est introuvable.'], Response::HTTP_BAD_REQUEST);
                    }
                    $value = $vehicle;
                } elseif ($key === 'startingAddress' || $key === 'arrivalAddress') {
                    //explode et json_encode des adresses
                    $address = explode('|', $value);
                    //mise en majuscule des villes
                    $address[1] = mb_convert_case($address[1], MB_CASE_UPPER, "UTF-8");
                    //Update dans $trip
                    $value = json_encode($address, true);
                }
                $trip->$setter($value);
            }
        }

        //Si on a changé le véhicule, on envoie un mail
        if ($dataRequest["vehicle"] !== $originalVehicleId)
        {
            //Envoi du mail type changeTripVehicle à tous les participants
            foreach ($users as $userForMailing) {
                $strToReplace = [
                    "pseudo" => $userForMailing['pseudo'],
                    "brand" => $trip->getVehicle()->getBrand(),
                    "model" => $trip->getVehicle()->getModel(),
                    "color" => $trip->getVehicle()->getColor()
                ];
                $this->mailService->sendEmail($user->getEmail(), 'changeTripVehicle', $strToReplace);
            }
        }

        $returnMessage = [
            "error" => false,
            "message" => "Modifié avec succès",
            "httpStatus" => Response::HTTP_OK
        ];

        retour:

        $trip->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        //S'il y a des participants, on ajoute le count($users) pour MongoDB
        count($users) ? $nbParticipant = count($users): $nbParticipant = 0;
        //Modification dans mongoDB
        $this->tripMongoService->update($trip->getId(), [
            'id_covoiturage' => $trip->getId(),
            'startingAddress' => $trip->getStartingAddress(),
            'arrivalAddress' => $trip->getArrivalAddress(),
            'startingAt' => $trip->getStartingAt(),
            'duration' => $trip->getDuration(),
            'nbCredit' => $trip->getNbCredit(),
            'nbPlaceRemaining' => $trip->getNbPlaceRemaining(),
            'nbParticipant' => $nbParticipant,
            'user' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'grade' => $user->getGrade(),
            ],
            'vehicle' => [
                'energy' => $trip->getVehicle()?->getEnergy()?->getLibelle(),
                'isEco' => $trip->getVehicle()?->getEnergy()?->isEco(),
            ],
        ]);

        return new JsonResponse(['error' => $returnMessage['error'], "message" => $returnMessage['message']], $returnMessage['httpStatus']);
    }

}
