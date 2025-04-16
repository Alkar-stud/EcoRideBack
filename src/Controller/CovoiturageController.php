<?php

namespace App\Controller;

use App\Entity\Covoiturage;
use App\Entity\User;
use App\Entity\CovoiturageStatus;
use App\Entity\Vehicle;
use App\Repository\CovoiturageRepository;
use App\Service\CovoiturageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface   $manager,
        private readonly CovoiturageRepository    $repository,
        private readonly SerializerInterface      $serializer,
        private readonly CovoiturageService       $covoiturageService, // Injection du service

    )
    {
    }

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

        // Vérification si le véhicule existe et appartient au CurrentUser
        if (!$vehicle || $vehicle->getOwner() !== $user) {
            return new JsonResponse(['error' => 'Le véhicule est introuvable ou n\'appartient pas à l\'utilisateur actuel.'], Response::HTTP_FORBIDDEN);
        }

        // Recherche de l'entité EcoRide avec le libelle "DEFAULT_COVOITURAGE_STATUS"
        $defaultCovoiturageStatusId = intval($this->covoiturageService->getDefaultStatus()) ?? 1;

        // Récupération de l'objet CovoiturageStatus correspondant
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


        //Il faut maintenant l'ajouter dns mongoDB



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
    public function showAllOwner(#[CurrentUser] ?User $user): JsonResponse
    {
        $covoiturages = $this->repository->findBy(['owner' => $user->getId(), 'isClosed' => null], ['startingAt' => 'ASC']);

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
            "cancel"  => ["initial"=> ["coming"], "become"=>"cancelled"]
        ];

        $covoiturage = $this->repository->findOneBy(['id' => $id, 'owner' => $user->getId()]);
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        //Vérification si l'action est demandée et est possible
        if (!isset($dataRequest['action']) || !array_key_exists($dataRequest['action'], $possibleActions)) {
            return new JsonResponse(
                [
                    'error' => 'L\'action demandée n\'est pas réalisable'
                ],
                Response::HTTP_FORBIDDEN
            );
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
                // Vérification si le covoiturage est à l'état initial
                if ($initialStatus !== $defaultCovoiturageStatusId) {
                    return new JsonResponse(
                        [
                            'error' => 'Seuls les covoiturages à l\'état `' . $defaultCovoiturageStatusLibelle . '` peuvent être modifiés.'
                        ],
                        Response::HTTP_FORBIDDEN
                    );
                }

                // Empêcher la modification de certains champs
                if (isset($dataRequest['status']) || isset($dataRequest['createdAt']) || isset($dataRequest['updatedAt']) || isset($dataRequest['owner']) || isset($dataRequest['isClosed'])) {
                    return new JsonResponse([
                        'error' => 'Certains champs ne peuvent pas être modifiés.'
                    ], Response::HTTP_FORBIDDEN);
                }

                //Modification
                $covoiturage = $this->serializer->deserialize(
                    $request->getContent(),
                    Covoiturage::class,
                    'json',
                    [AbstractNormalizer::OBJECT_TO_POPULATE => $covoiturage]
                );

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

                $covoiturage->setStatus($status);
            }




            //Si action == update, il faut modifier dans mongoDB, sinon le supprimer ou vérifier qu'il n'est plus.



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

}
