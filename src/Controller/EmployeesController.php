<?php

namespace App\Controller;

use App\Entity\Ride;
use App\Entity\User;
use App\Repository\RideRepository;
use App\Repository\ValidationRepository;
use App\Service\MongoService;
use App\Service\RideService;
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
    private const NOTICE_REFUSED = 'REFUSED';
    private const NOTICE_VALIDATED = 'VALIDATED';

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RideRepository         $repositoryRide,
        private readonly ValidationRepository   $repositoryValidation,
        private readonly SerializerInterface    $serializer,
        private readonly MongoService           $mongoService,
        private readonly RideService            $rideService,

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

            //Si le user est le dernier à valider, on vérifie pour payer les différentes parties
            $this->rideService->checkValidationsAndPayment($ride);

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


    #[Route('/showNotices', name: 'showNotices', methods: ['GET'])]
    #[OA\Get(
        path:"/api/ecoride/employee/showNotices",
        summary:"Récupération de la liste des avis à valider"
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des avis à valider trouvée avec succès'
    )]
    public function showNotices(): ?JsonResponse
    {
        $notices = $this->mongoService->getAwaitingValidationNotices();

        $data = $this->serializer->serialize($notices, 'json');

        return new JsonResponse($data, 200, [], true);
    }

    #[Route('/validateNotice', name: 'validateNotice', methods: ['POST'])]
    #[OA\Post(
        path: "/api/ecoride/employee/validateNotice",
        summary: "Valider ou refuser un avis",
        requestBody: new RequestBody(
            description: "Données nécessaires pour valider ou refuser un avis",
            required: true,
            content: [new MediaType(mediaType: "application/json",
                schema: new Schema(properties: [
                    new Property(
                        property: "id",
                        type: "string",
                        example: "60f1a2b3c4d5e6f7g8h9i0j1"
                    ),
                    new Property(
                        property: "action",
                        type: "string",
                        example: "VALIDATED"
                    )
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Avis traité avec succès'
    )]
    #[OA\Response(
        response: 400,
        description: 'Requête invalide'
    )]
    #[OA\Response(
        response: 404,
        description: 'Avis non trouvé'
    )]
    public function validateNotice(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        // Décoder les données de la requête
        $dataRequest = $this->serializer->decode($request->getContent(), 'json');

        // Vérifier que les paramètres requis sont présents
        if (!isset($dataRequest['id']) || !isset($dataRequest['action'])) {
            return new JsonResponse(['message' => 'Les paramètres id et action sont requis'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que l'action est valide
        if ($dataRequest['action'] !== self::NOTICE_REFUSED && $dataRequest['action'] !== self::NOTICE_VALIDATED) {
            return new JsonResponse(
                ['message' => 'L\'action doit être soit ' . self::NOTICE_REFUSED . ' soit ' . self::NOTICE_VALIDATED],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Récupérer l'avis
        $notice = $this->mongoService->findOneNotice($dataRequest['id']);
        if (!$notice) {
            return new JsonResponse(['message' => 'Avis non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Passer directement l'action en majuscules comme attendu par updateNotice
            $result = $this->mongoService->updateNotice(['id' => $dataRequest['id']], $user, $dataRequest['action']);

            if (!$result) {
                return new JsonResponse(['message' => 'Échec de la mise à jour de l\'avis'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $actionFr = $dataRequest['action'] === self::NOTICE_VALIDATED ? 'validé' : 'refusé';
            return new JsonResponse(['message' => 'L\'avis a été ' . $actionFr], Response::HTTP_OK);
        } catch (MongoDBException|Throwable $e) {
            return new JsonResponse(['message' => 'Erreur lors du traitement : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
