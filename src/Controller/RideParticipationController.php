<?php

namespace App\Controller;

use App\Document\MongoRideNotice;
use App\Entity\Ride;
use App\Entity\User;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use App\Repository\ValidationRepository;
use App\Service\MailService;
use App\Service\MongoService;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\MongoDBException;
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
use Throwable;

#[Route('/api/ride', name: 'app_api_ride_')]
#[OA\Tag(name: 'RideParticipation')]
#[Areas(["default"])]
final class RideParticipationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RideRepository         $repository,
        private readonly ValidationRepository   $repositoryValidation,
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
        private readonly MailService            $mailService,
    ) {
    }

    /**
     * Recherche de covoiturages selon des critères
     * @throws Exception
     */
    #[Route('/search', name: 'search', methods: ['POST'])]
    #[OA\Post(
        path: "/api/ride/search",
        summary: "Recherche de covoiturages avec critères. Lieu de départ et d'arrivée ainsi que la date sont obligatoires",
        requestBody: new RequestBody(
            description: "Critères de recherche de covoiturages.",
            required: true,
            content: [new MediaType(mediaType: "application/json",
                schema: new Schema(properties: [
                    new Property(
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
                        property: "maxDuration",
                        type: "integer",
                        example: 120
                    ),
                    new Property(
                        property: "maxPrice",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "MinDriverGrade",
                        type: "integer",
                        example: 3
                    ),
                    new Property(
                        property: "isEco",
                        type: "boolean",
                        example: true
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage(s) trouvé(s) avec succès',
        content: new Model(type: Ride::class, groups: ['ride_search'])
    )]
    #[OA\Response(
        response: 404,
        description: 'Aucun covoiturage trouvé'
    )]
    public function search(Request $request): JsonResponse
    {
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        // Validation des données d'entrée
        $validationResult = $this->validateSearchRequest($dataRequest);
        if ($validationResult !== true) {
            return $validationResult;
        }

        // Transformation des adresses
        $dataRequest = $this->transformAddresses($dataRequest);

        // Recherche des trajets
        $rides = $this->repository->findBySomeField($dataRequest);

        // Si aucun trajet trouvé, chercher les trajets les plus proches
        if (!$rides) {
            return $this->findNearestRides($dataRequest);
        }

        // Format de retour cohérent avec findNearestRides
        $jsonContent = $this->serializer->serialize($rides, 'json', ['groups' => 'ride_search']);
        $responseData = [
            'rides' => json_decode($jsonContent, true),
            'message' => 'ok'
        ];

        return new JsonResponse($responseData, Response::HTTP_OK);
    }

    /**
     * Ajoute un utilisateur à un covoiturage
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/{rideId}/addUser', name: 'addUser', methods: ['PUT'])]
    #[OA\Put(
        path: "/api/ride/{rideId}/addUser",
        summary: "Ajout d'un participant",
    )]
    #[OA\Response(
        response: 200,
        description: 'Vous avez été ajouté à ce covoiturage'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé'
    )]
    #[OA\Response(
        response: 402,
        description: 'Vous n\'avez pas assez de credit pour participer à ce covoiturage.'
    )]
    #[IsGranted('ROLE_USER')]
    public function addUser(#[CurrentUser] ?User $user, int $rideId): JsonResponse
    {
        $ride = $this->repository->findOneBy(['id' => $rideId]);
        if (!$ride) {
            return $this->createJsonError('Ce covoiturage n\'existe pas', Response::HTTP_NOT_FOUND);
        }

        // Vérifications préalables
        if ($ride->getStatus() !== RideStatus::getDefaultStatus()) {
            return $this->createJsonError('L\'état de ce covoiturage ne permet pas l\'ajout de participants', Response::HTTP_FORBIDDEN);
        }

        if ($ride->getPassenger()->contains($user)) {
            return $this->createJsonResponse('Vous êtes déjà inscrit à ce covoiturage.', Response::HTTP_OK);
        }

        if ($ride->getNbPlacesAvailable() <= $ride->getPassenger()->count()) {
            return $this->createJsonResponse('Il n\'y a plus de place disponible pour ce covoiturage.', Response::HTTP_OK);
        }

        if ($user->getCredits() < $ride->getPrice()) {
            return $this->createJsonResponse('Vous n\'avez pas assez de credit pour participer à ce covoiturage.', Response::HTTP_PAYMENT_REQUIRED);
        }

        // Traitement de l'ajout
        $this->processUserAddition($user, $ride);

        return $this->createJsonResponse('Vous avez été ajouté à ce covoiturage', Response::HTTP_OK);
    }

    /**
     * Retire un utilisateur d'un covoiturage
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/{rideId}/removeUser', name: 'removeUser', methods: ['PUT'])]
    #[OA\Put(
        path: "/api/ride/{rideId}/removeUser",
        summary: "Retrait d'un participant",
    )]
    #[OA\Response(
        response: 200,
        description: 'Vous avez été retiré de ce covoiturage'
    )]
    #[OA\Response(
        response: 400,
        description: 'Covoiturage non trouvé'
    )]
    #[IsGranted('ROLE_USER')]
    public function removeUser(#[CurrentUser] ?User $user, int $rideId): JsonResponse
    {
        $ride = $this->repository->findOneBy(['id' => $rideId]);
        if (!$ride) {
            return $this->createJsonError('Ce covoiturage n\'existe pas', Response::HTTP_NOT_FOUND);
        }

        // Vérifications préalables
        if ($ride->getStatus() !== RideStatus::getDefaultStatus()) {
            return $this->createJsonError('L\'état de ce covoiturage ne permet pas de retirer des participants', Response::HTTP_FORBIDDEN);
        }

        if (!$ride->getPassenger()->contains($user)) {
            return $this->createJsonResponse('Vous n\'êtes pas inscrit à ce covoiturage.', Response::HTTP_OK);
        }

        // Traitement du retrait
        $this->processUserRemoval($user, $ride);

        return $this->createJsonResponse('Vous avez été retiré à ce covoiturage', Response::HTTP_OK);
    }

    /**
     * Ajoute un avis sur un covoiturage
     * @throws Throwable
     * @throws MongoDBException
     */
    #[Route('/{rideId}/addNotice', name: 'rideValidate', methods: ['POST'])]
    #[OA\Post(
        path: "/api/ride/{rideId}/addNotice",
        summary: "Note et commentaire suite à la fin du covoiturage",
        requestBody: new RequestBody(
            description: "Note et commentaire suite à la fin du covoiturage",
            required: true,
            content: [new MediaType(mediaType: "application/json",
                schema: new Schema(properties: [
                    new Property(
                        property: "grade",
                        type: "integer",
                        example: 5
                    ),
                    new Property(
                        property: "title",
                        type: "text",
                        example: "titre de l'avis"
                    ),
                    new Property(
                        property: "content",
                        type: "text",
                        example: "Contenu de l'avis"
                    )
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Validation envoyée avec succès',
        content: new Model(type: MongoRideNotice::class)
    )]
    #[IsGranted('ROLE_USER')]
    public function rideNotice(#[CurrentUser] ?User $user, Request $request, int $rideId): JsonResponse
    {
        $ride = $this->repository->find($rideId);
        if (!$ride || !$ride->getPassenger()->contains($user)) {
            return $this->createJsonError('Ce covoiturage n\'existe pas ou vous n\'êtes pas un passager de celui-ci.', Response::HTTP_NOT_FOUND);
        }

        // Vérification de la validation préalable
        $validation = $this->repositoryValidation->findOneBy(['ride' => $rideId, 'passenger' => $user]);
        if (!$validation) {
            return $this->createJsonError('Vous devez valider le bon déroulement du covoiturage avant de mettre une note.', Response::HTTP_BAD_REQUEST);
        }

        // Vérification d'un avis déjà existant
        $notice = $this->mongoService->searchNotice($user, $ride);
        if ($notice) {
            return $this->createJsonError('Vous avez déjà envoyé un avis.', Response::HTTP_BAD_REQUEST);
        }

        // Traitement de l'avis
        $notice = $this->serializer->decode($request->getContent(), 'json');
        if ($notice['grade'] < 0 || $notice['grade'] > 5) {
            return $this->createJsonError('La note doit être entre 0 et 5', Response::HTTP_BAD_REQUEST);
        }

        $this->mongoService->addNotice($notice, $user, $ride);

        return $this->createJsonResponse('Votre avis sera publié une fois validé.', Response::HTTP_OK);
    }

    /**
     * Modifie le statut d'un covoiturage
     * @throws TransportExceptionInterface
     */
    #[Route('/{rideId}/{action}', name: 'rideAction', methods: ['PUT'])]
    #[OA\Put(
        path: "/api/ride/{rideId}/{action}",
        summary: "Changement de statut d'un covoiturage",
    )]
    #[OA\Response(
        response: 200,
        description: 'Le covoiturage est maintenant en statuts {action}'
    )]
    #[OA\Response(
        response: 400,
        description: 'Covoiturage non trouvé'
    )]
    #[IsGranted('ROLE_USER')]
    public function rideAction(#[CurrentUser] ?User $user, int $rideId, string $action): JsonResponse
    {
        $ride = $this->repository->findOneBy(['id' => $rideId, 'driver' => $user]);
        if (!$ride) {
            return $this->createJsonError('Ce covoiturage n\'existe pas.', Response::HTTP_NOT_FOUND);
        }

        // Vérification de la validité de l'action demandée
        $possibleActions = RideStatus::getPossibleActions();
        if (!array_key_exists($action, $possibleActions)) {
            return $this->createJsonError('Action non reconnue.', Response::HTTP_BAD_REQUEST);
        }

        $currentStatus = strtolower($ride->getStatus());
        if (!in_array($currentStatus, array_map('strtolower', $possibleActions[$action]['initial']))) {
            $availableActions = $this->getAvailableActions($possibleActions, $currentStatus);
            return $this->createJsonError(
                'Le covoiturage ne peut pas être modifié vers cet état. La ou les action(s) possible(s) est/sont : "' . $availableActions . '"',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Traitement de l'action demandée
        $actionResult = $this->processRideAction($ride, $action);
        if ($actionResult !== true) {
            return $actionResult;
        }

        $ride->setStatus(strtoupper($possibleActions[$action]['become']));
        $this->manager->flush();

        $labels = [];
        foreach (RideStatus::cases() as $case) {
            $labels[$case->name] = $case->getLabel();
        }

        return $this->createJsonResponse('Le covoiturage est maintenant en statut "' . $labels[$ride->getStatus()] . '"', Response::HTTP_OK);
    }

    /**
     * Valide les données de recherche
     * @throws Exception
     */
    private function validateSearchRequest(array $dataRequest): bool|JsonResponse
    {
        if (!isset($dataRequest['startingAddress']) || !isset($dataRequest['arrivalAddress']) || !isset($dataRequest['startingAt'])) {
            return $this->createJsonError('Champs obligatoires manquants', Response::HTTP_BAD_REQUEST);
        }

        $startingAt = new DateTimeImmutable($dataRequest['startingAt']);
        $today = (new DateTimeImmutable())->setTime(0, 0, 0);
        if ($startingAt < $today) {
            return $this->createJsonError('La date doit être supérieure ou égale à la date du jour.', Response::HTTP_BAD_REQUEST);
        }

        return true;
    }

    /**
     * Transforme les adresses en champs distincts
     */
    private function transformAddresses(array $dataRequest): array
    {
        $dataRequest['startingStreet'] = $dataRequest['startingAddress']['street'] ?? '';
        $dataRequest['startingPostCode'] = $dataRequest['startingAddress']['postcode'];
        $dataRequest['startingCity'] = $dataRequest['startingAddress']['city'];
        unset($dataRequest['startingAddress']);

        $dataRequest['arrivalStreet'] = $dataRequest['arrivalAddress']['street'] ?? '';
        $dataRequest['arrivalPostCode'] = $dataRequest['arrivalAddress']['postcode'];
        $dataRequest['arrivalCity'] = $dataRequest['arrivalAddress']['city'];
        unset($dataRequest['arrivalAddress']);

        return $dataRequest;
    }

    /**
     * Recherche les trajets les plus proches
     * @throws Exception
     */
    /**
     * Recherche les trajets les plus proches
     * @throws Exception
     */
    private function findNearestRides(array $dataRequest): JsonResponse
    {
        $today = (new DateTimeImmutable())->setTime(0, 0, 0);
        $searchDate = new DateTimeImmutable($dataRequest['startingAt']);

        // Recherche du covoiturage le plus proche avant la date demandée (>= aujourd'hui)
        $rideBefore = $this->repository->findOneClosestBefore($dataRequest, $searchDate, $today);

        // Recherche du covoiturage le plus proche après la date demandée
        $rideAfter = $this->repository->findOneClosestAfter($dataRequest, $searchDate);

        $ridesFound = [];
        if ($rideBefore) $ridesFound[] = $rideBefore;
        if ($rideAfter) $ridesFound[] = $rideAfter;

        if (!empty($ridesFound)) {
            $jsonContent = $this->serializer->serialize($ridesFound, 'json', ['groups' => 'ride_search']);

            // Convertir en tableau pour ajouter l'information supplémentaire
            $responseData = [
                'rides' => json_decode($jsonContent, true),
                'message' => 'Aucun covoiturage trouvé à la date demandée. Voici les trajets les plus proches disponibles.'
            ];

            return new JsonResponse($responseData, Response::HTTP_OK);
        }

        return new JsonResponse(['error' => true, 'message' => 'Aucun covoiturage trouvé'], Response::HTTP_NOT_FOUND);
    }

    /**
     * Traite l'ajout d'un utilisateur à un covoiturage
     * @throws MongoDBException
     * @throws Throwable
     */
    private function processUserAddition(User $user, Ride $ride): void
    {
        // Déduction des crédits
        $user->setCredits($user->getCredits() - $ride->getPrice());
        $ride->addPassenger($user);
        $this->manager->flush();

        // Ajout du mouvement de crédit dans MongoDB
        $this->mongoService->addMovementCreditsForRides($ride, $user, 'add', 'addPassenger', $ride->getPrice());
    }

    /**
     * Traite le retrait d'un utilisateur d'un covoiturage
     * @throws MongoDBException
     * @throws Throwable
     */
    private function processUserRemoval(User $user, Ride $ride): void
    {
        // Recréditation
        $user->setCredits($user->getCredits() + $ride->getPrice());
        $ride->removePassenger($user);
        $this->manager->flush();

        // Ajout du mouvement de crédit dans MongoDB
        $this->mongoService->addMovementCreditsForRides($ride, $user, 'withdraw', 'removePassenger', $ride->getPrice());
    }

    /**
     * Traite l'action demandée sur un covoiturage
     * @throws TransportExceptionInterface
     */
    private function processRideAction(Ride $ride, string $action): bool|JsonResponse
    {
        if ($action == 'start') {
            if ($ride->getStartingAt()->format('Y-m-d') != (new DateTimeImmutable())->format('Y-m-d')) {
                return $this->createJsonError('Le covoiturage ne peut démarrer que le jour où il est déclaré commencer.', Response::HTTP_BAD_REQUEST);
            }

            if ($ride->getPassenger()->isEmpty()) {
                return $this->createJsonError('Il n\'y a aucun participant inscrit à ce covoiturage.', Response::HTTP_BAD_REQUEST);
            }

            $ride->setActualDepartureAt(new DateTimeImmutable());
        } elseif ($action == 'stop') {
            $this->notifyPassengersForValidation($ride);
            $ride->setActualArrivalAt(new DateTimeImmutable());
        }

        return true;
    }

    /**
     * Envoie des notifications aux passagers pour validation
     * @throws TransportExceptionInterface
     */
    private function notifyPassengersForValidation(Ride $ride): void
    {
        foreach ($ride->getPassenger() as $userPassenger) {
            $this->mailService->sendEmail(
                $userPassenger->getEmail(),
                'passengerValidation',
                [
                    'pseudoPassenger' => $userPassenger->getPseudo(),
                    'rideDate' => $ride->getStartingAt()->format('d/m/Y'),
                    'pseudoDriver' => $ride->getDriver()->getPseudo()
                ]
            );
        }
    }

    /**
     * Récupère les actions disponibles pour un statut donné
     */
    private function getAvailableActions(array $possibleActions, string $currentStatus): string
    {
        $actionsDisponibles = '';
        foreach ($possibleActions as $actionName => $actionData) {
            if (in_array($currentStatus, array_map('strtolower', $actionData['initial']))) {
                if ($actionName != 'update') {
                    $actionsDisponibles .= $actionName . ' ou ';
                }
            }
        }
        return substr($actionsDisponibles, 0, -4);
    }

    /**
     * Crée une réponse JSON avec un message d'erreur
     */
    private function createJsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    /**
     * Crée une réponse JSON avec un message
     */
    private function createJsonResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}