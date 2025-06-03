<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Validation;
use App\Enum\RideStatus;
use App\Repository\EcorideRepository;
use App\Repository\RideRepository;
use App\Repository\ValidationRepository;
use App\Service\MongoService;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\MongoDBException;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Areas;
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

#[Route('/api/validation', name: 'app_api_validation_')]
#[OA\Tag(name: 'RideValidation')]
#[Areas(["default"])]
final class ValidationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RideRepository         $repository,
        private readonly EcoRideRepository      $repositoryEcoRide,
        private readonly ValidationRepository   $repositoryValidation,
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
    )
    {
    }


    /**
     * @throws MongoDBException
     * @throws Throwable
     */
    #[Route('/add/{rideId}', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path:"/api/validation/add/{rideId}",
        summary:"Validation du bon déroulement d'un covoiturage",
        requestBody :new RequestBody(
            description: "Données pour la validation du bon déroulement d'un covoiturage",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [
                    new Property(
                        property: "isAllOk",
                        type: "boolean",
                        example: true
                    ),
                    new Property(
                        property: "content",
                        type: "string",
                        example: "Explications pourquoi cela s'est passé."
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Validation du covoiturage ajouté avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Covoiturage non trouvé'
    )]
    public function add(#[CurrentUser] ?User $user, Request $request, $rideId): JsonResponse
    {
        $validation = $this->serializer->deserialize($request->getContent(), Validation::class, 'json');

        //Il ne peut avoir qu'une seule validation par passager par covoiturage.
        //Vérification s'il y a déjà une validation pour ce passager pour ce covoiturage.
        $isOneValidation = $this->repositoryValidation->findOneBy(['ride' => $rideId, 'passenger' => $user]);

        if ($isOneValidation)
        {
            return new JsonResponse(['message' => 'Vous avez déjà envoyé une validation. Si vous voulez la modifier, veuillez utiliser le formulaire de contact.'], Response::HTTP_BAD_REQUEST);
        }

        //Récupération du covoiturage
        $ride = $this->repository->findOneBy(['id' => $rideId]);

        $validation->setPassenger($user);
        $validation->setRide($ride);

        //Si isAllOk est === true, c'est que tout s'est bien passé, donc on clôture par le user
        if ($validation->isAllOk() === true)
        {
            $validation->setIsClosed(true);
            $validation->setClosedBy($user);
        } else {
            $ride->setStatus(RideStatus::getBadExpStatus());
            $validation->setIsClosed(false);
            $validation->setClosedBy(null);
        }

        $validation->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($validation);
        $this->manager->flush();

        //Si le user est le dernier à valider, ET que le statut n'est pas BADEXP ou AWAITINGVALIDATION (BADEXP en cours de contrôle par un employé), on clôture le covoiturage et on paye le chauffeur en retirant la commission
        //Compte des passagers
        $nbPassengers = count($ride->getPassenger());
        //Compte des validations
        $nbValidations = count($ride->getValidations());

        if ($nbValidations === $nbPassengers && $ride->getStatus() !== RideStatus::getBadExpStatus() && $ride->getStatus() != RideStatus::getBadExpStatusProcessing())
        {
            //Statut de fin par défaut
            $ride->setStatus(RideStatus::getFinishedStatus());
            //On paie le chauffeur, $nbPassengers * $ride→price – la commission
            //Récupération de la commission, parameterValue de l'entité EcoRide dont le libelle est PLATFORM_COMMISSION_CREDIT.
            $platformCommission = $this->repositoryEcoRide->findOneBy(['libelle' => 'PLATFORM_COMMISSION_CREDIT']);
            if (!$platformCommission) {
                $platformCommission->setParameterValue(0);
            }
            $payment = ($nbPassengers * $ride->getPrice());

            //On met à jour le crédit du chauffeur
            $ride->getDriver()->setCredits($ride->getDriver()->getCredits() + $payment - $platformCommission->getParameterValue());
            //On incrémente le crédit total de EcoRide
            $platformTotalCredits = $this->repositoryEcoRide->findOneBy(['libelle' => 'TOTAL_CREDIT']);
            $platformTotalCredits->setParameterValue($platformCommission->getParameterValue() + $platformTotalCredits->getParameterValue());

            $this->mongoService->addMovementCreditsForRides($ride, $ride->getDriver(), 'withdraw', 'Paiement du chauffeur pour le covoiturage ' . $ride->getId(), $ride->getDriver()->getCredits());
            $this->mongoService->addMovementCreditsForRides($ride, $ride->getDriver(), 'withdraw', 'Commission de la plateforme pour le covoiturage ' . $ride->getId(), $platformCommission->getParameterValue());

            $this->manager->persist($ride->getDriver());

            $this->manager->flush();
        }

        return new JsonResponse(['message' => 'Votre validation a bien été envoyée.' . ($ride->getStatus() === RideStatus::getBadExpStatus() ? ' Un employé va vérifier votre réclamation.':'')], Response::HTTP_OK);
    }
}
