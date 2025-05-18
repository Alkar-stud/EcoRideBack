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

#[Route('/api/ride', name: 'app_api_ride_')]
#[OA\Tag(name: 'Ride')]
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


}