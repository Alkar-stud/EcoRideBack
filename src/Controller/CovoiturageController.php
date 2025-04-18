<?php

namespace App\Controller;

use App\Entity\Covoiturage;
use App\Entity\User;
use App\Entity\CovoiturageStatus;
use App\Entity\Vehicle;
use App\Repository\CovoiturageRepository;
use App\Service\CovoiturageMongoService;
use App\Service\CovoiturageService;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/covoiturage', name: 'app_api_covoiturage_')]
final class CovoiturageController extends AbstractController{

    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly CovoiturageRepository   $repository,
        private readonly SerializerInterface     $serializer,
        private readonly CovoiturageService      $covoiturageService,
        private readonly CovoiturageMongoService $covoiturageMongoService,
    )
    {
    }

    /**
     * @throws ORMException
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $covoiturage = $this->serializer->deserialize($request->getContent(), Covoiturage::class, 'json');

        // Récupération de l'ID du véhicule depuis le JSON
        $data = json_decode($request->getContent(), true);
        $vehicleId = $data['vehicle'] ?? null;

        // Vérification si l'ID du véhicule est fourni
        if (!$vehicleId) {
            return new JsonResponse(['error' => 'L\'ID du véhicule est requis.'], Response::HTTP_BAD_REQUEST);
        }
        // Récupération du véhicule depuis la base de données
        $vehicle = $this->manager->getRepository(Vehicle::class)->find($vehicleId);
        // Ajout d'un contrôle pour éviter la création d'un nouvel objet Vehicle
        if (!$vehicle) {
            return new JsonResponse(['error' => 'Le véhicule spécifié est introuvable.'], Response::HTTP_BAD_REQUEST);
        }
        // Vérification si le véhicule existe et appartient au CurrentUser
        if ($vehicle->getOwner() !== $user) {
            return new JsonResponse(['error' => 'Le véhicule est introuvable ou n\'appartient pas à l\'utilisateur actuel.'], Response::HTTP_FORBIDDEN);
        }

        // Recherche de l'entité EcoRide avec le libelle "DEFAULT_COVOITURAGE_STATUS"
        $defaultCovoiturageStatusId = intval($this->covoiturageService->getDefaultStatus()) ?? 1;
        // Récupération du texte du statut CovoiturageStatus correspondant
        $defaultCovoiturageStatus = $this->manager->getRepository(CovoiturageStatus::class)->find($defaultCovoiturageStatusId);

        // Attribution du statut par défaut au covoiturage
        $covoiturage->setStatus($defaultCovoiturageStatus);

        // Persister le covoiturage dans la base de données
        $covoiturage->setVehicle($vehicle);

        //owner doit être l'ID de CurrentUser
        $covoiturage->setOwner($user);

        // Vérification si le propriétaire du véhicule est bien l'utilisateur actuel
        if ($vehicle->getOwner() !== $user) {
            return new JsonResponse(['error' => 'Le propriétaire du véhicule n\'est pas l\'utilisateur actuel.'], Response::HTTP_FORBIDDEN);
        }

        // Attribution de la date de création
        $covoiturage->setCreatedAt(new DateTimeImmutable());
        $this->manager->persist($covoiturage);
        $this->manager->flush();

        // Forcer le rechargement de l'entité User pour éviter les problèmes de synchronisation
        $this->manager->refresh($user);

        // Vérification des préférences après rechargement
        $preferences = $user->getPreferences()->toArray();
        if (empty($preferences)) {
            return new JsonResponse(['error' => 'Aucune préférence trouvée pour cet utilisateur.'], Response::HTTP_BAD_REQUEST);
        }

        $user->getPreferences()->toArray();

        // Sérialiser les préférences de l'utilisateur pour MongoDB
        $preferencesArray = $this->serializer->normalize($user->getPreferences(), null, ['groups' => 'user_details']);

        // Ajouter les préférences sérialisées dans MongoDB
        $this->covoiturageMongoService->add([
            'id_covoiturage' => $covoiturage->getId(),
            'status' => $covoiturage->getStatus()?->getLibelle(),
            'startingAddress' => $covoiturage->getStartingAddress(),
            'arrivalAddress' => $covoiturage->getArrivalAddress(),
            'startingAt' => $covoiturage->getStartingAt(),
            'tripDuration' => $covoiturage->getTripDuration(),
            'nbCredit' => $covoiturage->getNbCredit(),
            'nbPlaceRemaining' => $covoiturage->getNbPlaceRemaining(),
            // Données utilisateur
            'user' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'preferences' => $preferencesArray,
                'grade' => $user->getGrade(),
            ],
            // Données véhicule
            'vehicle' => [
                'brand' => $vehicle->getBrand(),
                'model' => $vehicle->getModel(),
                'color' => $vehicle->getColor(),
                'energy' => $vehicle->getEnergy()?->getLibelle(),
                'isEco' => $vehicle->getEnergy()?->isEco(),
            ],
            'createdAt' => $covoiturage->getCreatedAt(),
        ]);


        return new JsonResponse(
            [
                'id'  => $covoiturage->getId(),
                'status'  => [
                    'libelle' => $covoiturage->getStatus()?->getLibelle()
                ],
                'startingAddress'  => $covoiturage->getStartingAddress(),
                'arrivalAddress'  => $covoiturage->getArrivalAddress(),
                'startingAt' => $covoiturage->getStartingAt(),
                'tripDuration' => $covoiturage->getTripDuration(),
                'nbCredit' => $covoiturage->getNbCredit(),
                'nbPlace' => $covoiturage->getNbPlaceRemaining(),
                'createdAt' => $covoiturage->getCreatedAt()
            ],
            Response::HTTP_OK
        );
    }

    #[Route('/list/', name: 'showAllOwner', methods: 'GET')]
    public function showAllOwner(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        // Pagination pour les covoiturages
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $covoiturages = $this->repository->findBy(
            ['owner' => $user->getId(), 'isClosed' => null],
            ['startingAt' => 'ASC'],
            $limit,
            ($page - 1) * $limit
        );

        if ($covoiturages) {
            $responseData = array_map(fn($covoiturage) => [
                'id' => $covoiturage->getId(),
                'status' => $covoiturage->getStatus()?->getLibelle(),
                'startingAddress' => $covoiturage->getStartingAddress(),
                'arrivalAddress' => $covoiturage->getArrivalAddress(),
                'startingAt' => $covoiturage->getStartingAt(),
                'tripDuration' => $covoiturage->getTripDuration(),
                'nbCredit' => $covoiturage->getNbCredit(),
                'nbPlaceRemaining' => $covoiturage->getNbPlaceRemaining(),
                'createdAt' => $covoiturage->getCreatedAt(),
                'updatedAt' => $covoiturage->getUpdatedAt(),
            ], $covoiturages);

            return new JsonResponse($responseData, Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }

    #[Route('/{id}', name: 'show', methods: 'GET')]
    public function showById(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        $covoiturage = $this->repository->findOneBy(['id' => $id]);
        if ($covoiturage) {

            return new JsonResponse(
                [
                    'id' => $covoiturage->getId(),
                    'status' => $covoiturage->getStatus()?->getLibelle(),
                    'startingAddress' => $covoiturage->getStartingAddress(),
                    'arrivalAddress' => $covoiturage->getArrivalAddress(),
                    'startingAt' => $covoiturage->getStartingAt(),
                    'tripDuration' => $covoiturage->getTripDuration(),
                    'nbCredit' => $covoiturage->getNbCredit(),
                    'nbPlaceRemaining' => $covoiturage->getNbPlaceRemaining(),
                    'createdAt' => $covoiturage->getCreatedAt(),
                    'updatedAt' => $covoiturage->getUpdatedAt(),
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(#[CurrentUser] ?User $user, int $id, Request $request): JsonResponse
    {
        //action possible selon l'état du covoiturage avec l'état suivant selon l'action demandée
        $possibleActions = [
            "update"  => ["initial"=> ["coming"], "become"=>"coming"],
            "start"  => ["initial"=> ["coming"], "become"=>"progressing"],
            "stop"  => ["initial"=> ["progressing"], "become"=>"validationProcess"],
            "badxp"  => ["initial"=> ["validationProcess"], "become"=>"awaitingValidation"],
            "finish"  => ["initial"=> ["awaitingValidation","validationProcess"], "become"=>"finished"],
            "cancel"  => ["initial"=> ["coming"], "become"=>"canceled"]
        ];

        //Récupération du covoiturage par son ID
        $covoiturage = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        //Vérification si l'action est demandée et est possible
        $validationResponse = $this->validateEditRequest($dataRequest, $possibleActions);
        if ($validationResponse !== null) {
            return $validationResponse;
        }

        if ($covoiturage) {
            $initialStatus = $covoiturage->getStatus()?->getId();
            // Recherche de l'entité EcoRide avec le libelle "DEFAULT_COVOITURAGE_STATUS"
            $defaultCovoiturageStatusId = intval($this->covoiturageService->getDefaultStatus()) ?? 1;

            // Récupération de l'objet CovoiturageStatus correspondant
            $defaultCovoiturageStatus = $this->manager->getRepository(CovoiturageStatus::class)->find($defaultCovoiturageStatusId);
            $defaultCovoiturageStatusLibelle = $defaultCovoiturageStatus?->getLibelle();

            //Tableau associatif des codes => id de covoiturageStatus
            $covoiturageStatusArray = $this->manager->getRepository(CovoiturageStatus::class)->createQueryBuilder('cs')
                ->select('cs.code, cs.id')
                ->getQuery()
                ->getResult();

            $covoiturageStatusMap = [];
            foreach ($covoiturageStatusArray as $status) {
                $covoiturageStatusMap[$status['code']] = $status['id'];
            }

            //Demande de modification du covoiturage
            if ($dataRequest["action"] === 'update')
            {
                return $this->handleUpdateAction($covoiturage, $user, $dataRequest, $defaultCovoiturageStatusId, $defaultCovoiturageStatusLibelle);
            }

            //demande de démarrage du covoiturage
            elseif ($dataRequest['action'] === 'start') {
                //Vérification si c'est possible selon l'état initial
                if (!in_array($covoiturage->getStatus()?->getCode(), $possibleActions['start']["initial"]))
                {
                    return new JsonResponse(
                        [
                            'error' => 'Le covoiturage ne peut pas être démarré.'
                        ],
                        Response::HTTP_FORBIDDEN
                    );
                }

                $statusId = $covoiturageStatusMap[$possibleActions[$dataRequest['action']]["become"]];
                $status = $this->manager->getRepository(CovoiturageStatus::class)->find($statusId);

                if (!$status) {
                    return new JsonResponse([
                        'error' => 'Le statut spécifié est introuvable.'
                    ], Response::HTTP_BAD_REQUEST);
                }
                // Supprimer le covoiturage de MongoDB car une fois démarré les visiteurs ne doivent plus le trouver
                $success = $this->covoiturageMongoService->delete($covoiturage->getId());
                $covoiturage->setStatus($status);
                $covoiturage->setUpdatedAt(new DateTimeImmutable());
                $this->manager->flush();

                return new JsonResponse(
                    [
                        'message' => 'Le covoiturage est démarré.'
                    ],
                    Response::HTTP_OK
                );
            }

            //demande de fin du covoiturage arrivée
            elseif ($dataRequest['action'] === 'stop') {
                //Vérification si c'est possible selon l'état initial
                if (!in_array($covoiturage->getStatus()?->getCode(), $possibleActions['stop']["initial"]))
                {
                    return new JsonResponse(
                        [
                            'error' => 'Le covoiturage ne peut pas être arrêté.'
                        ],
                        Response::HTTP_FORBIDDEN
                    );
                }


                $statusId = $covoiturageStatusMap[$possibleActions[$dataRequest['action']]["become"]];
                $status = $this->manager->getRepository(CovoiturageStatus::class)->find($statusId);

                if (!$status) {
                    return new JsonResponse([
                        'error' => 'Le statut spécifié est introuvable.'
                    ], Response::HTTP_BAD_REQUEST);
                }

                $covoiturage->setStatus($status);
            }

            //demande d'annulation du covoiturage
            elseif ($dataRequest['action'] === 'cancel') {
                return $this->handleCancelAction($covoiturage, $possibleActions);
            }

            $covoiturage->setUpdatedAt(new DateTimeImmutable());

            $this->manager->flush();

            return new JsonResponse(
                [
                    'id' => $covoiturage->getId(),
                    'status' => $covoiturage->getStatus()?->getLibelle(),
                    'startingAddress' => $covoiturage->getStartingAddress(),
                    'arrivalAddress' => $covoiturage->getArrivalAddress(),
                    'startingAt' => $covoiturage->getStartingAt(),
                    'tripDuration' => $covoiturage->getTripDuration(),
                    'nbCredit' => $covoiturage->getNbCredit(),
                    'nbPlaceRemaining' => $covoiturage->getNbPlaceRemaining(),
                    'createdAt' => $covoiturage->getCreatedAt(),
                    'updatedAt' => $covoiturage->getUpdatedAt(),
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);


    }

    //Pour gérer l'action "update"

    /**
     * @throws Exception
     */
    private function handleUpdateAction(Covoiturage $covoiturage, User $user, array $dataRequest, int $defaultCovoiturageStatusId, string $defaultCovoiturageStatusLibelle): JsonResponse
    {

        $returnMessage = "";
        // Vérification si le covoiturage est à l'état initial
        if ($covoiturage->getStatus()?->getId() !== $defaultCovoiturageStatusId) {
            $returnMessage = [
                "type_message" => 'error',
                "message" => "'error' => 'Seuls les covoiturages à l\'état `' . $defaultCovoiturageStatusLibelle . '` peuvent être modifiés.'",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }

        // Vérification si des passagers sont enregistrés dans la table covoiturage_user
        $passengerCount = $this->manager->getConnection()->createQueryBuilder()
            ->select('COUNT(*) as count')
            ->from('covoiturage_user')
            ->where('covoiturage_id = :covoiturageId')
            ->setParameter('covoiturageId', $covoiturage->getId())
            ->executeQuery()
            ->fetchOne();
        if ($passengerCount > 0 && isset($dataRequest['nbPlaceRemaining']) === false) {
            $returnMessage = [
                "message" => "'error' => 'Le covoiturage ne peut pas être modifié car des passagers sont déjà enregistrés.'",
                "httpStatus" => Response::HTTP_FORBIDDEN
            ];
            goto retour;
        }

        // Empêcher la modification de certains champs
        $restrictedFields = ['status', 'createdAt', 'updatedAt', 'owner', 'isClosed'];
        foreach ($restrictedFields as $field) {
            if (isset($dataRequest[$field])) {
                $returnMessage = [
                    "type_message" => 'error',
                    "message" => "'error' => 'Certains champs ne peuvent pas être modifiés manuellement.",
                    "httpStatus" => Response::HTTP_FORBIDDEN
                ];
                goto retour;
            }
        }
        // Vérification si le véhicule appartient à l'utilisateur
        $vehicleId = $dataRequest['vehicle'] ?? null;
        $vehicle = $this->manager->getRepository(Vehicle::class)->find($vehicleId);
        if ($vehicleId) {
            if (!$vehicle || $vehicle->getOwner() !== $user) {
                $returnMessage = [
                    "type_message" => 'error',
                    "message" => "'error' => 'Le véhicule est introuvable ou n\'appartient pas à l\'utilisateur actuel.'",
                    "httpStatus" => Response::HTTP_FORBIDDEN
                ];
                goto retour;
            }

            $covoiturage->setVehicle($vehicle);
        }

        // Modification des données du covoiturage
        // Exclure le champ 'vehicle' de la désérialisation
        $this->serializer->deserialize(
            json_encode($dataRequest),
            Covoiturage::class,
            'json',
            [
                AbstractNormalizer::OBJECT_TO_POPULATE => $covoiturage,
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['vehicle']
            ]
        );

        $covoiturage->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        // Récupérer les préférences existantes de l'utilisateur depuis MongoDB
        $existingData = $this->covoiturageMongoService->findById($covoiturage->getId());
        $existingPreferences = $existingData['user']['preferences'] ?? [];

        // Mise à jour des données dans MongoDB en conservant les préférences existantes
        $this->covoiturageMongoService->update($covoiturage->getId(), [
            'id_covoiturage' => $covoiturage->getId(),
            'status' => $covoiturage->getStatus()?->getLibelle(),
            'startingAddress' => $covoiturage->getStartingAddress(),
            'arrivalAddress' => $covoiturage->getArrivalAddress(),
            'startingAt' => $covoiturage->getStartingAt(),
            'tripDuration' => $covoiturage->getTripDuration(),
            'nbCredit' => $covoiturage->getNbCredit(),
            'nbPlaceRemaining' => $covoiturage->getNbPlaceRemaining(),
            'user' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'photo' => $user->getPhoto(),
                'preferences' => $existingPreferences, // Conserver les préférences existantes
                'grade' => $user->getGrade(),
            ],
            'vehicle' => [
                'brand' => $covoiturage->getVehicle()?->getBrand(),
                'model' => $covoiturage->getVehicle()?->getModel(),
                'color' => $covoiturage->getVehicle()?->getColor(),
                'energy' => $covoiturage->getVehicle()?->getEnergy()?->getLibelle(),
                'isEco' => $covoiturage->getVehicle()?->getEnergy()?->isEco(),
            ],
            'createdAt' => $covoiturage->getCreatedAt(),
            'updatedAt' => $covoiturage->getUpdatedAt(),
        ]);

        $returnMessage = [
            "type_message" => 'message',
            "message" => 'Covoiturage mis à jour avec succès.',
            "httpStatus" => Response::HTTP_OK
        ];


        retour:
        return new JsonResponse([$returnMessage['type_message'] => $returnMessage['message']], $returnMessage['httpStatus']);
    }

    private function handleCancelAction(Covoiturage $covoiturage, array $possibleActions): JsonResponse
    {
        // Vérification si le statut actuel correspond au statut initial requis pour l'annulation
        $currentStatusCode = $covoiturage->getStatus()?->getCode();
        if (!in_array($currentStatusCode, $possibleActions['cancel']['initial'])) {
            return new JsonResponse(
                [
                    'error' => 'Le covoiturage ne peut pas être annulé car son statut actuel ne le permet pas.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        // Mise à jour du statut en "cancelled"
        $statusId = $this->manager->getRepository(CovoiturageStatus::class)
            ->findOneBy(['code' => $possibleActions['cancel']['become']])?->getId();

        if (!$statusId) {
            return new JsonResponse([
                'error' => 'Le statut "cancelled" est introuvable.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $status = $this->manager->getRepository(CovoiturageStatus::class)->find($statusId);
        $covoiturage->setStatus($status);
        $covoiturage->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        // Supprimer le covoiturage de MongoDB car une fois annulé les visiteurs ne doivent plus le trouver
        $this->covoiturageMongoService->delete($covoiturage->getId());

        return new JsonResponse([
            'message' => 'Le covoiturage a été annulé avec succès.'
        ], Response::HTTP_OK);
    }

    #[Route('/show_unique/{id}', name: 'show_unique', methods: ['GET'])]
    public function ShowUnique(int $id): JsonResponse
    {
        // Récupération des données depuis MongoDB en fonction de l'id_covoiturage
        $covoiturageData = $this->covoiturageMongoService->findById($id);

        // Vérification si les données existent
        if (!$covoiturageData) {
            return new JsonResponse(['error' => 'Covoiturage introuvable dans MongoDB.'], Response::HTTP_NOT_FOUND);
        }

        // Extraction des informations nécessaires
        $response = [
            'startingAt' => $covoiturageData['startingAt'] ?? null,
            'arrivalAddress' => $covoiturageData['arrivalAddress'] ?? null,
        ];

        return new JsonResponse($response, Response::HTTP_OK);
    }










    #[Route('/{id}/start', name: 'start', methods: ['PUT'])]
    public function start(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        //Est-ce que cette action est possible
        $isActionPossible = $this->isActionPossible('start', $id, $user);

        if ($isActionPossible['error'] != 'ok')
        {
            return new JsonResponse(['error' => $isActionPossible['message']], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['message' => 'Action réalisable, devient : ' . $isActionPossible['become']], Response::HTTP_OK);
    }

    #[Route('/{id}/stop', name: 'stop', methods: ['PUT'])]
    public function stop(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        //Est-ce que cette action est possible
        $isActionPossible = $this->isActionPossible('stop', $id, $user);

        if (!$isActionPossible)
        {
            return new JsonResponse(['message' => 'Action réalisable'], Response::HTTP_OK);
        }

        return new JsonResponse(['message' => 'Action non réalisable'], Response::HTTP_FORBIDDEN);
    }


    private function isActionPossible($action, $id, $user): array
    {
        //action possible selon l'état du covoiturage avec l'état suivant selon l'action demandée
        $possibleActions = [
            "update"  => ["initial"=> ["coming"], "become"=>"coming"],
            "start"  => ["initial"=> ["coming"], "become"=>"progressing"],
            "stop"  => ["initial"=> ["progressing"], "become"=>"validationProcess"],
            "badxp"  => ["initial"=> ["validationProcess"], "become"=>"awaitingValidation"],
            "finish"  => ["initial"=> ["awaitingValidation","validationProcess"], "become"=>"finished"],
            "cancel"  => ["initial"=> ["coming"], "become"=>"canceled"]
        ];

        //Vérification si l'action demandée est possible
        $requestIsValide = $this->validateEditRequest($action, $possibleActions);
        if (!$requestIsValide)
        {
            return ['error' => 'unknown_action', "message" => 'Cette action est impossible'];
        }

        //Récupération de l'entité
        $covoiturage = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        //Si le covoiturage n'existe pas
        if (!$covoiturage) {
            return ['error' => 'unknown_covoiturage', "message" => 'Ce covoiturage n\'existe pas'];
        }
        //Si user n'est pas owner
        if ($covoiturage->getOwner() !== $user) {
            return ['error' => 'owner', "message" => 'Ce covoiturage n\'existe pas dans vos covoiturages'];
        }
        //Si l'état initial ne le permet pas
        if (!in_array($covoiturage->getStatus()->getCode(), $possibleActions[$action]["initial"]))
        {
            //Définition des réponses en fonction de l'état
            $returnMessage = match ($action) {
                'start' => 'Le covoiturage ne peut pas être démarré.',
                'stop' => 'Le covoiturage ne peut pas être arrêté.',
                default => 'Cette action est impossible dans cet état.',
            };
            return [
                'error' => 'initial_status',
                "message" => $returnMessage
            ];
        }

        //Comme c'est possible, on persist dans mysql et on delete dans mongodb si $action == start
        //Tableau associatif des codes => id de covoiturageStatus
        $covoiturageStatusArray = $this->manager->getRepository(CovoiturageStatus::class)->createQueryBuilder('cs')
            ->select('cs.code, cs.id')
            ->getQuery()
            ->getResult();

        $covoiturageStatusMap = [];
        foreach ($covoiturageStatusArray as $status) {
            $covoiturageStatusMap[$status['code']] = $status['id'];
        }

        $statusId = $covoiturageStatusMap[$possibleActions[$action]["become"]];
        $status = $this->manager->getRepository(CovoiturageStatus::class)->find($statusId);
        if (!$status) {
            return ['error' => 'status', "message" => 'Le statut spécifié est introuvable'];
        }
        // Supprimer le covoiturage de MongoDB car une fois démarré les visiteurs ne doivent plus le trouver
        if ($action == 'start') {
            $this->covoiturageMongoService->delete($covoiturage->getId());
        }
        $covoiturage->setStatus($status);
        $covoiturage->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return ['error' => 'ok', 'become' => $possibleActions[$action]["become"]];
    }

    //Valide si l'action existe et est possible.
    private function validateEditRequest($action, array $possibleActions): bool
    {
        if (!isset($action) || !array_key_exists($action, $possibleActions)) {
            return false;
        }
        return true;
    }



}
