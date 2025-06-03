<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use App\Repository\ValidationRepository;
use App\Service\MongoService;
use App\Service\RidePayments;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\MongoDBException;
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
use Throwable;

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
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
        private readonly RidePayments           $ridePayments,
    )
    {
    }

    #[Route('/showValidations', name: 'showValidations', methods: ['GET'])]
    #[OA\Get(
        path:"/api/ecoride/employee/showValidations",
        summary:"Récupération de la liste des covoiturages qui se sont mal déroulé"
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des covoiturages trouvée avec succès',
        content: new Model(type: Ride::class, groups: ['ride_read', 'ride_control'])
    )]
    public function showValidations(#[CurrentUser] ?User $user): JsonResponse
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

    /**
     * @throws Throwable
     * @throws MongoDBException
     */
    #[Route('/supportValidation/{idValidation}', name: 'supportValidation', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/employee/supportValidation/{idValidation}",
        summary:"Prise en charge d'un covoiturage en attente de validation",
        requestBody :new RequestBody(
            description: "Données pour la prise en charge d'un covoiturage en attente de validation",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [
                    new Property(
                        property: "closeContent",
                        type: "string",
                        example: "En cours de contact avec le chauffeur"
                    ),
                    new Property(
                        property: "isClosed",
                        type: "boolean",
                        example: false
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Covoiturage en cours de prise en charge'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé'
    )]
    public function supportValidation(#[CurrentUser] ?User $user, Request $request, int $idValidation): JsonResponse
    {
        //Changement du statut du covoiturage en AWAITINGVALIDATION et supportBy de l'entité validation si isClosed == false, getFinishedStatus si true
        $validation = $this->repositoryValidation->findOneBy(['id' => $idValidation]);
        if (!$validation)
        {
            return new JsonResponse(['message' => 'Validation non trouvée.'], Response::HTTP_NOT_FOUND);
        }
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');
        if (!isset($dataRequest['closeContent']))
        {
            return new JsonResponse(['message' => 'Il faut mettre un commentaire de prise en charge.'], Response::HTTP_NOT_FOUND);
        }
        $validation->setSupportBy($user);
        $validation->setCloseContent($dataRequest['closeContent']);
        $validation->setUpdatedAt(new DateTimeImmutable());

        $ride = $this->repositoryRide->findOneBy(['id' => $validation->getRide()->getId()]);

        if (isset($dataRequest['isClosed']) && $dataRequest['isClosed'] === true)
        {
            $validation->setIsClosed(true);
            $validation->setClosedBy($user);

            //Compte des passagers
            $nbPassengers = count($ride->getPassenger());
            //Compte des validations
            $nbValidations = count($ride->getValidations());

            if ($nbValidations === $nbPassengers && ($ride->getStatus() !== RideStatus::getBadExpStatus() || $ride->getStatus() != RideStatus::getBadExpStatusProcessing()))
            {

                //On paie le chauffeur, $nbPassengers * $ride→price – la commission
                $this->ridePayments->driverPayment($ride, $nbPassengers);

                $this->manager->persist($ride->getDriver());

                $this->manager->flush();
            }

            $returnMessage = 'Validation clôturée. Covoiturage terminé.';
        } else {
            $ride->setStatus('AWAITINGVALIDATION');
            $dataRequest['isClosed'] = false;
            $returnMessage = 'Validation prise en charge.';
        }

        $this->manager->persist($validation);

        $this->manager->persist($ride);
        $this->manager->flush();

        //Ajout du commentaire de prise en charge dans MongoDB pour historique
        $this->mongoService->addValidationHistory($ride, $validation, $user, $dataRequest['closeContent'], $dataRequest['isClosed']);

        return new JsonResponse(['message' => $returnMessage], Response::HTTP_OK);
    }


    public function showNotices(Request $request): ?JsonResponse
    {

        return null;
    }


}
