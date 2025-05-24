<?php

namespace App\Controller;


use App\Entity\Ride;
use App\Entity\User;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use App\Service\MongoService;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
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
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
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
    public function search(Request $request): JsonResponse
    {
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        //Vérifications
        //Champs obligatoire, city de startingAddress, city de  arrivalAddress et date
        if (!isset($dataRequest['startingAddress']) || !isset($dataRequest['arrivalAddress']) || !isset($dataRequest['startingAt']))
        {
            return new JsonResponse(['message' => 'Champs obligatoires manquants'], Response::HTTP_BAD_REQUEST);
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

        if(!$rides) {
            return new JsonResponse(['message' => 'Aucun covoiturage trouvé'], Response::HTTP_NOT_FOUND);
        }


        $jsonContent = $this->serializer->serialize($rides, 'json', ['groups' => 'ride_search']);

        return new JsonResponse($jsonContent, Response::HTTP_OK, [], true);



    }

    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/{id}/addUser', name: 'addUser', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ride/{Id}/addUser",
        summary:"Ajout d'un participant",

    )]
    #[OA\Response(
        response: 200,
        description: 'Vous avez été ajouté à ce covoiturage'
    )]
    #[OA\Response(
        response: 400,
        description: 'Covoiturage non trouvé'
    )]
    #[OA\Response(
        response: 402,
        description: 'Vous n\'avez pas assez de credit pour participer à ce covoiturage.'
    )]
    public function addUser(#[CurrentUser] ?User $user, int $id): JsonResponse
    {
        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $id]);

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
            //Vérification que le solde de credit est suffisant
            if ($user->getCredits() < $ride->getPrice()) {
                return new JsonResponse(['message' => 'Vous n\'avez pas assez de credit pour participer à ce covoiturage.'], Response::HTTP_PAYMENT_REQUIRED);
            }
            //On retire les crédits au passager
            $user->setCredits($user->getCredits() - $ride->getPrice());
            $ride->addPassenger($user);
            $this->manager->flush();

            //Ajout du prix dans le crédit temp sur mongoDB

            $this->mongoService->addMovementCreditsForRides($ride, $user, 'add', 'addPassenger');

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
    public function removeUser(#[CurrentUser] ?User $user, int $rideId): JsonResponse
    {
        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $rideId]);

        if ($ride)
        {
            //Si le statut est le statut initial ET que le user n'a pas déjà été ajouté
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
                $this->mongoService->addMovementCreditsForRides($ride, $user, 'withdraw', 'removePassenger');


                return new JsonResponse(['message' => 'Vous avez été retiré à ce covoiturage'], Response::HTTP_OK);
            }
            return new JsonResponse(['message' => 'L\'état de ce covoiturage ne permet pas de retirer des participants'], Response::HTTP_FORBIDDEN);
        }
        return new JsonResponse(['message' => 'Ce covoiturage n\'existe pas'], Response::HTTP_NOT_FOUND);
    }

}
