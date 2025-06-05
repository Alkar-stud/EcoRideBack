<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EcorideRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\PasswordService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Areas;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;

#[Route('/api/ecoride/admin', name: 'app_api_ecoride_admin/')]
#[OA\Tag(name: 'Admin')]
#[Areas(["ecoride"])]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface  $manager,
        private readonly EcorideRepository       $repositoryEcoRide,
        private readonly UserRepository          $repositoryUser,
        private readonly SerializerInterface     $serializer,
        private readonly MailService             $mailService,
        private readonly PasswordService         $passwordService,
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    #[OA\Post(
        path:"/api/ecoride/admin/add",
        summary:"Inscription d'un nouvel employé",
        requestBody :new RequestBody(
            description: "Données de l'employé à inscrire",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [
                    new Property(
                        property: "pseudo",
                        type: "string",
                        example: "Pseudo"
                    ),
                    new Property(
                        property: "email",
                        type: "string",
                        example: "adresse@email.com"
                    )
                ],
                type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Employé inscrit avec succès'
    )]
    #[OA\Response(
        response: 400,
        description: 'Il manque un élément obligatoire.'
    )]
    #[OA\Response(
        response: 409,
        description: 'Ce compte existe déjà'
    )]
    public function add(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

        //Vérification que les champs sont tous renseignés
        if (null === $user->getPseudo() || null === $user->getEmail()) {
            return new JsonResponse(['message' => 'Informations incomplètes'], Response::HTTP_BAD_REQUEST);
        }
        //Génération du mot de passe envoyé par mail avec demande de le changer à la 1ère connexion
        $passGen = $this->passwordService->passwordGeneration(12);
        $user->setPassword($passwordHasher->hashPassword($user, $passGen));
        $user->setRoles(['ROLE_EMPOYEE']);
        $user->setIsPassenger(false);
        $user->setCreatedAt(new DateTimeImmutable());

        //Vérification de l'existence de l'utilisateur pour ne pas avoir d'email en double
        $existingUser = $this->manager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

        if ($existingUser) {
            return new JsonResponse(['message' => 'Ce compte existe déjà'], Response::HTTP_CONFLICT);
        }

        $this->manager->persist($user);
        $this->manager->flush();

        //envoi du mail au format texte confirmant la création du compte avec le mot de passe en invitant à le changer dès la première connexion
        $this->mailService->sendEmail($user->getEmail(), 'accountEmployeeCreate', ['pseudo' => $user->getPseudo(), 'pass' => $passGen]);

        return new JsonResponse(['message' => 'Compte pour ' . $user->getPseudo() . ' inscrit et mail avec mot de passe envoyé avec succès'], Response::HTTP_CREATED);
    }

    #[Route('/suspend/{id}', name: 'suspend', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/admin/suspend/{id}",
        summary:"Suspension d'un compte"
    )]
    #[OA\Response(
        response: 200,
        description: 'Compte suspendu avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Ce compte n\'existe pas'

    )]
    public function suspend(int $id): JsonResponse
    {
        //Pour suspendre un compte, il faut mettre isActive à false
        $user = $this->repositoryUser->findOneBy(['id' => $id]);

        $user->setIsActive(false);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(['message' => 'Compte suspendu avec succès'], Response::HTTP_OK);
    }

    #[Route('/reactivate/{id}', name: 'reactivate', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/admin/reactivate/{id}",
        summary:"Réactivation d'un compte"
    )]
    #[OA\Response(
        response: 200,
        description: 'Compte réactivé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'Ce compte n\'existe pas'

    )]
    public function reactivate(int $id): JsonResponse
    {
        //Pour réactiver un compte, il faut mettre isActive à false
        $user = $this->repositoryUser->findOneBy(['id' => $id]);

        $user->setIsActive(true);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(['message' => 'Compte réactivé avec succès'], Response::HTTP_OK);
    }

    #[Route('/checkCredits/{limit}', name: 'checkCredits', methods: ['GET'])]
    #[OA\Get(
        path:"/api/ecoride/admin/checkCredits/{limit}",
        summary:"Affichage des données statistiques."
    )]
    #[OA\Response(
        response: 200,
        description: 'Données affichées'
    )]
    #[OA\Response(
        response: 404,
        description: 'Aucunes données trouvées'

    )]
    public function checkCredits($limit): JsonResponse
    {
        //Récupération de la limite
        if ($limit != 'all') {
            $limit = intval($limit);
            if ($limit <= 0) {
                $limit = 12; // Valeur par défaut
            }
        }

        // Récupération de la commission
        $platformCommission = $this->repositoryEcoRide->findOneBy(['libelle' => 'PLATFORM_COMMISSION_CREDIT']);

        $query = $this->manager->createQuery(
            'SELECT r.startingAt AS rideDate, COUNT(r.id) AS nbRides
        FROM App\Entity\Ride r
        WHERE r.startingAt <= :startingAt
        ' . ($limit != 'all' ? 'AND r.startingAt >= :dateLimit' : '') . '
        GROUP BY r.startingAt
        ORDER BY r.startingAt ASC'
        );

        $query->setParameter('startingAt', date('Y-m-d H:i:s'));
        // Ajouter le paramètre dateLimit seulement si nécessaire
        if ($limit != 'all') {
            $dateLimit = new DateTime();
            $dateLimit->modify('-' . $limit . ' months');
            $query->setParameter('dateLimit', $dateLimit->format('Y-m-d H:i:s'));
        }

        $rawResults = $query->getResult();

        $rides = [];
        foreach ($rawResults as $result) {
            $date = $result['rideDate']->format('Y-m-d');
            $rides['dailyStats'][] = [
                'rideDate' => $date,
                'nbRides' => $result['nbRides'],
                'dailyGain' => $result['nbRides'] * $platformCommission->getParameterValue()
            ];
        }
        $rides['totalGain'] = count($rides['dailyStats']) * $platformCommission->getParameterValue();

        if (!$rides) {
            return new JsonResponse(['message' => 'Aucune donnée trouvée'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($rides, Response::HTTP_OK);
    }



}