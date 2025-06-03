<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Repository\EcorideRepository;
use App\Repository\RideRepository;
use App\Repository\ValidationRepository;
use App\Service\MongoService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Areas;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/ecoride/employee', name: 'app_api_ecoride_employee_')]
#[OA\Tag(name: 'Employees')]
#[Areas(["ecoride"])]
#[IsGranted('ROLE_EMPLOYEE')]
final class EmployeesController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RideRepository         $repositoryRide,
        private readonly ValidationRepository   $repositoryValidation,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/showValidations', name: 'showValidations', methods: ['GET'])]
    #[OA\Post(
        path:"/api/ecoride/employee/showValidations",
        summary:"Récupération de la liste des covoiturages qui se sont mal déroulé"
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des covoiturages trouvée avec succès',
        content: new Model(type: Ride::class, groups: ['ride_read', 'ride_control'])
    )]
    public function showValidations(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        $rides = $this->repositoryRide->findBy(['status' => ['BADEXP', 'AWAITINGVALIDATION']]);
        $filteredRides = array_filter($rides, function ($ride) use ($user) {
            // Exclure si le user est conducteur
            if ($ride->getDriver() && $ride->getDriver()->getId() === $user->getId()) {
                return false;
            }
            // Exclure si le user est passager
            foreach ($ride->getPassenger() as $passenger) {
                if ($passenger->getId() === $user->getId()) {
                    return false;
                }
            }
            return true;
        });
        $data = $this->serializer->serialize($filteredRides, 'json', ['groups' => ['ride_read', 'ride_control']]);

        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/supportValidations/{idValidation}', name: 'supportValidations', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/employee/supportValidations/{idValidation}",
        summary:"Prise en charge d'un covoiturage en attente de validation"
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage pris en charge'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé'
    )]
    public function supportValidations(#[CurrentUser] ?User $user, int $idValidation): JsonResponse
    {
        //Changement du statut du covoiturage en AWAITINGVALIDATION et supportBy de l'entité validation
        $validation = $this->repositoryValidation->findOneBy(['id' => $idValidation]);
        if (!$validation)
        {
            return new JsonResponse(['message' => 'Validation non trouvée.'], Response::HTTP_NOT_FOUND);
        }
        $validation->setSupportBy($user);

        $this->manager->persist($validation);

        $ride = $this->repositoryRide->findOneBy(['id' => $validation->getRide()->getId()]);
        $ride->setStatus('AWAITINGVALIDATION');

        $this->manager->persist($ride);
        $this->manager->flush();

        return new JsonResponse(['message' => 'Validation prise en charge.'], Response::HTTP_OK);
    }


    public function showNotices(Request $request): ?JsonResponse
    {

        return null;
    }


}
