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
use Symfony\Contracts\Service\Attribute\Required;

#[Route('/api/ride/participation', name: 'app_api_ride_participation_')]
#[OA\Tag(name: 'RideParticipation')]
#[Areas(["default"])]
final class RideParticipationController extends AbstractController
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

    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(
        path:"/api/ride/participation/search",
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
                        property: "duration",
                        type: "integer",
                        example: 120
                    ),
                    new Property(
                        property: "maxPrice",
                        type: "integer",
                        example: 15
                    ),
                    new Property(
                        property: "DriverGrade",
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

    }

}