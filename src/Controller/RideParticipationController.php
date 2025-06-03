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
    )
    {
    }

    #[Route('/search', name: 'search', methods: ['POST'])]
    #[OA\Post(
        path:"/api/ride/search",
        summary:"Recherche de covoiturages avec critères. Lieu de départ et d'arrivée ainsi que la date sont obligatoires",
        requestBody :new RequestBody(
            description: "Critères de recherche de covoiturages.",
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
        response: 201,
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

        //Vérifications
        //Champs obligatoire, city de startingAddress, city de  arrivalAddress et date
        if (!isset($dataRequest['startingAddress']) || !isset($dataRequest['arrivalAddress']) || !isset($dataRequest['startingAt']))
        {
            return new JsonResponse(['message' => 'Champs obligatoires manquants'], Response::HTTP_BAD_REQUEST);
        }
        //la date doit être supérieure ou égale au jour
        $startingAt = new DateTimeImmutable($dataRequest['startingAt']);
        $today = (new \DateTimeImmutable())->setTime(0, 0, 0);
        if ($startingAt < $today)
        {
            return new JsonResponse(['message' => 'La date doit être supérieure ou égale à la date du jour .'], Response::HTTP_BAD_REQUEST);
        }

        //transformation des champs startingAddress en street, postcode et city
        $dataRequest['startingStreet'] = $dataRequest['startingAddress']['street'];
        $dataRequest['startingPostCode'] = $dataRequest['startingAddress']['postcode'];
        $dataRequest['startingCity'] = $dataRequest['startingAddress']['city'];
        unset($dataRequest['startingAddress']);
        $dataRequest['arrivalStreet'] = $dataRequest['arrivalAddress']['street'];
        $dataRequest['arrivalPostCode'] = $dataRequest['arrivalAddress']['postcode'];
        $dataRequest['arrivalCity'] = $dataRequest['arrivalAddress']['city'];
        unset($dataRequest['arrivalAddress']);

        //Champs facultatifs : isEco, maxPrice, maxDuration, MinDriverGrade

        //chercher à l'aide de $dataRequest
        $rides = $this->repository->findBySomeField($dataRequest);

        // Après la recherche principale
        if(!$rides) {
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
                return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
            }

            return new JsonResponse(['message' => 'Aucun covoiturage trouvé'], Response::HTTP_NOT_FOUND);
        }

        $jsonContent = $this->serializer->serialize($rides, 'json', ['groups' => 'ride_search']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);
    }

    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/{rideId}/addUser', name: 'addUser', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/{rideId}/addUser",
        summary:"Ajout d'un participant",

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
        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $rideId]);

        if (!$ride) {
            return new JsonResponse(['error' => true, 'message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
        }

        //Si le statut est le statut initial ET que le user n'a pas déjà été ajouté
        if ($ride->getStatus() === RideStatus::getDefaultStatus())
        {
            //Si le $user fait partie des participants
            if ($ride->getPassenger()->contains($user)) {
                // L'utilisateur fait partie des participants
                return new JsonResponse(['message' => 'Vous êtes déjà inscrit à ce covoiturage.'], Response::HTTP_OK);
            }
            //Vérification s'il y a encore de la place disponible
            if ($ride->getNbPlacesAvailable() <= $ride->getPassenger()->count()) {
                return new JsonResponse(['message' => 'Il n\'y a plus de place disponible pour ce covoiturage.'], Response::HTTP_OK);
            }
            //Vérification que le solde de credit est suffisant
            if ($user->getCredits() < $ride->getPrice()) {
                return new JsonResponse(['message' => 'Vous n\'avez pas assez de credit pour participer à ce covoiturage.'], Response::HTTP_PAYMENT_REQUIRED);
            }
            //On retire les crédits au passager
            $user->setCredits($user->getCredits() - $ride->getPrice());
            $ride->addPassenger($user);
            $this->manager->flush();

            //Ajout du prix dans le crédit temp sur mongoDB
            $this->mongoService->addMovementCreditsForRides($ride, $user, 'add', 'addPassenger', $ride->getPrice());

            return new JsonResponse(['message'=>'Vous avez été ajouté à ce covoiturage'], Response::HTTP_OK);
        }
        return new JsonResponse(['message'=>'L\'état de ce covoiturage ne permet pas l\'ajout de participants'], Response::HTTP_FORBIDDEN);
    }

    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/{rideId}/removeUser', name: 'removeUser', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/{rideId}/removeUser",
        summary:"Retrait d'un participant",

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
        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $rideId]);

        if ($ride)
        {
            //Si le statut est le statut initial ET que le user est bien inscrit
            if ($ride->getStatus() === RideStatus::getDefaultStatus()) {
                //Si le $user fait partie des participants
                if (!$ride->getPassenger()->contains($user)) {
                    // L'utilisateur ne fait pas partie des participants
                    return new JsonResponse(['message' => 'Vous n\'êtes pas inscrit à ce covoiturage.'], Response::HTTP_OK);
                }
                //On recrédite le user
                $user->setCredits($user->getCredits() + $ride->getPrice());
                //On met à jour le nombre de places restantes
                $ride->setNbPlacesAvailable($ride->getNbPlacesAvailable() + 1);
                $ride->removePassenger($user);
                $this->manager->flush();

                //Ajout du prix dans le crédit temp sur mongoDB
                $this->mongoService->addMovementCreditsForRides($ride, $user, 'withdraw', 'removePassenger', $ride->getPrice());

                return new JsonResponse(['message' => 'Vous avez été retiré à ce covoiturage'], Response::HTTP_OK);
            }
            return new JsonResponse(['message' => 'L\'état de ce covoiturage ne permet pas de retirer des participants'], Response::HTTP_FORBIDDEN);
        }
        return new JsonResponse(['message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
    }


    #[Route('/{rideId}/addNotice', name: 'rideValidate', methods: ['POST'])]
    #[OA\Post(
        path:"/api/ride/{rideId}/addNotice",
        summary:"Note et commentaire suite à la fin du covoiturage",
        requestBody :new RequestBody(
            description: "Note et commentaire suite à la fin du covoiturage",
            required: true,
            content: [new MediaType(mediaType:"application/json",
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
        //Récupération du covoiturage où le user fait partie des passagers
        $ride = $this->repository->find($rideId);
        if (!$ride || !$ride->getPassenger()->contains($user)) {
            return new JsonResponse(['message' => 'Ce covoiturage n\'existe pas ou vous n\'êtes pas un passager de celui-ci.'], Response::HTTP_NOT_FOUND);
        }

        //Il doit déjà y avoir une validation avant de déposer une note
        //Récupération de la validation
        $validation = $this->repositoryValidation->findOneBy(['ride' => $rideId, 'passenger' => $user]);

        if (!$validation)
        {
            return new JsonResponse(['message' => 'Vous devez valider le bon déroulement du covoiturage avant de mettre une note.'], Response::HTTP_BAD_REQUEST);
        }

        //

        //Vérification que le user n'a pas déjà envoyé un avis
        $notice = $this->mongoService->searchNotice($user, $ride);

        if ($notice)
        {
            return new JsonResponse(['message' => 'Vous avez déjà envoyé un avis.'], Response::HTTP_BAD_REQUEST);
        }

        $notice = $this->serializer->decode($request->getContent(), 'json');

        //grade doit être maximum de 5
        if ($notice['grade'] < 0 || $notice['grade'] > 5)
        {
            return new JsonResponse(['message' => 'La note doit être entre 0 et 5'], Response::HTTP_BAD_REQUEST);
        }

        // Création et insertion de l'avis MongoDB
        $this->mongoService->addNotice($notice, $user, $ride);

        return new JsonResponse(['Votre avis sera publié une fois validé.'], Response::HTTP_OK);

    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/{rideId}/{action}', name: 'rideAction', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/{rideId}/{action}",
        summary:"Changement de statut d'un covoiturage",
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
        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $rideId, 'driver' => $user]);

        if (!$ride) {
            return new JsonResponse(['message' => 'Ce covoiturage n\'existe pas.'], Response::HTTP_NOT_FOUND);
        }

        /*
         * Vérification si l'action demandée est possible
         */

        // Vérification du statut du covoiturage pour savoir si celui-ci permet la modification
        $possibleActions = RideStatus::getPossibleActions();
        // Si l'action n'est pas définie dans les actions possibles, on retourne une erreur
        if (!array_key_exists($action, $possibleActions)) {
            return new JsonResponse(
                ['message' => 'Action non reconnue.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $currentStatus = strtolower($ride->getStatus()); // Statut actuel du covoiturage

        $actionsDisponibles = '';
        foreach ($possibleActions as $actionName => $actionData) {
            if (in_array($currentStatus, array_map('strtolower', $actionData['initial']))) {
                if ($actionName != 'update') { $actionsDisponibles .= $actionName . ' ou '; }
            }
        }
        $actionsDisponibles = substr($actionsDisponibles, 0, -4);

        if (!in_array($currentStatus, $possibleActions[$action]['initial'])) {
            return new JSonResponse(
                ['message' => 'Le covoiturage ne peut pas être modifié vers cet état. La ou les action(s) possible(s) est/sont : "' . $actionsDisponibles . '"'],
                Response::HTTP_BAD_REQUEST
            );
        }


        /*
         * Traitement en fonction de l'action demandée
         */

        //Si l'action est start
        if ($action == 'start') {
            //On est bien le jour du départ
            if ($ride->getStartingAt()->format('Y-m-d') != (new DateTimeImmutable())->format('Y-m-d')) {
                return new JsonResponse(
                    ['message' => 'Le covoiturage ne peut démarrer que le jour où il est déclaré commencer.'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            //Vérification s'il y a des participants inscrits, pour ne pas démarrer un covoiturage sans passagers où la commission serait prise à la fin.
            if ($ride->getPassenger()->isEmpty()) {
                return new JsonResponse(['message' => 'Il n\'y a aucun participant inscrit à ce covoiturage.'], Response::HTTP_BAD_REQUEST);
            }
            $ride->setActualDepartureAt(new DateTimeImmutable);
        }
        //Si l'action est stop
        if ($action == 'stop') {
            //Mail type 'passengerValidation' à envoyer aux participants les invitant à se rendre dans leur espace "Mes covoiturages" pour valider le trajet
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
            $ride->setActualArrivalAt(new DateTimeImmutable);
        }

        $ride->setStatus(strtoupper($possibleActions[$action]['become']));

        $this->manager->flush();
        $labels = [];
        foreach (RideStatus::cases() as $case) {
            $labels[$case->name] = $case->getLabel();
        }

        return new JsonResponse(['message' => 'Le covoiturage est maintenant en statut "' . $labels[$ride->getStatus()] . '"'], Response::HTTP_OK);
    }

}
