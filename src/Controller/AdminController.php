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
        $user->setRoles(['ROLE_EMPLOYEE']);
        $user->setIsPassenger(false);
        $user->setCreatedAt(new DateTimeImmutable());

        //Vérification de l'existence de l'utilisateur pour ne pas avoir d'email en double
        $existingUser = $this->manager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

        if ($existingUser) {
            return new JsonResponse(['error' => true, 'message' => 'Ce compte existe déjà'], Response::HTTP_CONFLICT);
        }

        $this->manager->persist($user);
        $this->manager->flush();

        //envoi du mail au format texte confirmant la création du compte avec le mot de passe en invitant à le changer dès la première connexion
        $this->mailService->sendEmail($user->getEmail(), 'accountEmployeeCreate', ['pseudo' => $user->getPseudo(), 'pass' => $passGen]);

        return new JsonResponse(['success' => true, 'message' => 'Compte pour ' . $user->getPseudo() . ' inscrit et mail avec mot de passe envoyé avec succès'], Response::HTTP_CREATED);
    }


    #[Route('/listEmployees', name: 'list_employees', methods: ['GET'])]
    #[OA\Get(
        path: "/api/ecoride/admin/listEmployees",
        summary: "Liste paginée de tous les employés",
        parameters: [
            new OA\Parameter(
                name: "page",
                description: "Numéro de page (défaut : 1)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1)
            ),
            new OA\Parameter(
                name: "limit",
                description: "Nombre d'éléments par page (défaut : 10)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "integer", default: 10)
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: "Liste des employés"
    )]
    public function listEmployees(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, (int)$request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $qb = $this->repositoryUser->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%employee%')
            ->orderBy('u.isActive', 'DESC') // Les actifs d'abord, les inactifs à la fin
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $users = $qb->getQuery()->getResult();

        $total = $this->repositoryUser->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%employee%')
            ->getQuery()
            ->getSingleScalarResult();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'email' => $user->getEmail(),
                'photo' => $user->getPhoto(),
                'grade' => $user->getGrade(),
                'isActive' => $user->isActive(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'data' => $data
        ], Response::HTTP_OK);
    }


    #[Route('/searchUser', name: 'search_user', methods: ['POST'])]
    #[OA\Post(
        path: "/api/ecoride/admin/searchUser",
        summary: "Recherche un utilisateur par email ou pseudo (employé par défaut)",
        parameters: [
            new OA\Parameter(
                name: "searchParameter",
                description: "Adresse email ou pseudo de l'utilisateur",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "isEmployee",
                description: "Filtrer sur les employés (true par défaut)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean", default: true)
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: "Utilisateur trouvé ou non",
        content: new OA\JsonContent(
            oneOf: [
                new OA\Schema( // Succès
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer", example: 42),
                            new OA\Property(property: "pseudo", type: "string", example: "JeanDupont"),
                            new OA\Property(property: "email", type: "string", example: "jean.dupont@email.com"),
                            new OA\Property(property: "isActive", type: "boolean", example: true),
                        ])
                    ],
                    type: "object"
                ),
                new OA\Schema( // Erreur
                    properties: [
                        new OA\Property(property: "error", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Aucun utilisateur trouvé")
                    ],
                    type: "object"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Paramètre de recherche manquant",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "error", type: "boolean", example: true),
                new OA\Property(property: "message", type: "string", example: "Veuillez fournir un email ou un pseudo")
            ],
            type: "object"
        )
    )]
    public function searchUser(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $searchParameter = $data['searchParameter'] ?? $request->query->get('searchParameter');
        $isEmployee = $data['isEmployee'] ?? $request->query->getBoolean('isEmployee', true);

        if (!$searchParameter) {
            return new JsonResponse(['error' => true, 'message' => 'Veuillez fournir un email ou un pseudo'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->repositoryUser->createQueryBuilder('u')
            ->where('u.pseudo = :search OR u.email = :search')
            ->setParameter('search', $searchParameter)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return new JsonResponse(['error' => true, 'message' => 'Aucun utilisateur trouvé'], Response::HTTP_OK);
        }

        $roles = $user->getRoles();


        if ($isEmployee) {
            // On ne veut que les employés
            if (!in_array('ROLE_EMPLOYEE', $roles)) {
                return new JsonResponse(['error' => true, 'message' => 'Aucun employé trouvé'], Response::HTTP_OK);
            }
        } else {
            // On veut tout sauf les employés
            if (in_array('ROLE_EMPLOYEE', $roles)) {
                return new JsonResponse(['error' => true, 'message' => 'Aucun utilisateur trouvé'], Response::HTTP_OK);
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'pseudo' => $user->getPseudo(),
                'email' => $user->getEmail(),
                'isActive' => $user->isActive(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/setActive/{id}', name: 'set_active', methods: ['PUT'])]
    #[OA\Put(
        path:"/api/ecoride/admin/setActive/{id}",
        summary:"(Dés)activation d'un compte",
        parameters: [
            new OA\Parameter(
                name: "active",
                description: "true pour activer, false pour suspendre",
                in: "query",
                required: true,
                schema: new OA\Schema(type: "boolean")
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Statut du compte mis à jour'
    )]
    #[OA\Response(
        response: 400,
        description: 'Paramètre "active" manquant ou invalide'
    )]
    public function setActive(int $id, Request $request): JsonResponse
    {
        $active = $request->query->getBoolean('active', false);

        if (!is_bool($active)) {
            return new JsonResponse(['error' => true, 'message' => 'Paramètre "active" manquant ou invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->repositoryUser->findOneBy(['id' => $id]);

        if (!$user) {
            return new JsonResponse(['error' => true, 'message' => 'Ce compte n\'existe pas'], Response::HTTP_OK);
        }

        $user->setIsActive($active);
        $user->setUpdatedAt(new DateTimeImmutable());
        $this->manager->persist($user);
        $this->manager->flush();

        $message = $active ? 'Compte réactivé avec succès' : 'Compte suspendu avec succès';

        return new JsonResponse(['success' => true, 'message' => $message], Response::HTTP_OK);
    }

    #[Route('/checkCredits/{limit}', name: 'checkCredits', methods: ['GET'])]
    #[OA\Get(
        path:"/api/ecoride/admin/checkCredits/{limit}",
        summary:"Affichage des données statistiques.",
        parameters: [
            new OA\Parameter(
                name: "limit",
                description: "Nombre de mois à prendre en compte (ou 'all' pour tout). Par défaut : 12",
                in: "path",
                required: false,
                schema: new OA\Schema(type: "string", default: "12")
            )
        ]
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

        $finishedStatus = \App\Enum\RideStatus::getFinishedStatus();

        // Récupération des covoiturages terminés selon la période
        $dql = '
            SELECT r.arrivalAt
            FROM App\Entity\Ride r
            WHERE r.status = :status
            ' . ($limit != 'all' ? 'AND r.arrivalAt >= :dateLimit' : '') . '
            ORDER BY r.arrivalAt ASC
        ';

        $query = $this->manager->createQuery($dql)
            ->setParameter('status', $finishedStatus);

        if ($limit != 'all') {
            $dateLimit = new \DateTime();
            $dateLimit->modify('-' . $limit . ' months');
            $query->setParameter('dateLimit', $dateLimit->format('Y-m-d H:i:s'));
        }

        $rawResults = $query->getResult();

        // Regroupement par date (sans l'heure)
        $stats = [];
        foreach ($rawResults as $result) {
            $date = $result['arrivalAt']->format('Y-m-d');
            if (!isset($stats[$date])) {
                $stats[$date] = 0;
            }
            $stats[$date]++;
        }

        // Construction du tableau de réponse
        $rides['dailyStats'] = [];
        foreach ($stats as $date => $nbRides) {
            $rides['dailyStats'][] = [
                'rideDate' => $date,
                'nbRides' => $nbRides,
                'dailyGain' => $nbRides * $platformCommission->getParameterValue()
            ];
        }
        $rides['totalGain'] = array_sum(array_column($rides['dailyStats'], 'dailyGain'));

// Récupération du crédit de bienvenue
        $welcomeCredit = $this->repositoryEcoRide->findOneBy(['libelle' => 'WELCOME_CREDIT']);

// Récupération des dates d'inscription des utilisateurs
        $dql = '
    SELECT u.createdAt
    FROM App\Entity\User u
    WHERE u.createdAt IS NOT NULL
    ' . ($limit != 'all' ? 'AND u.createdAt >= :dateLimit' : '') . '
    ORDER BY u.createdAt ASC
';

        $query = $this->manager->createQuery($dql);

        if ($limit != 'all') {
            $dateLimit = new \DateTime();
            $dateLimit->modify('-' . $limit . ' months');
            $query->setParameter('dateLimit', $dateLimit->format('Y-m-d H:i:s'));
        }

        $userResults = $query->getResult();

// Regroupement par date
        $userStats = [];
        foreach ($userResults as $result) {
            $date = $result['createdAt']->format('Y-m-d');
            if (!isset($userStats[$date])) {
                $userStats[$date] = 0;
            }
            $userStats[$date]++;
        }

// Construction du tableau de réponse
        $rides['userStats'] = [];
        foreach ($userStats as $date => $nbUsers) {
            $rides['userStats'][] = [
                'userDate' => $date,
                'nbUsers' => $nbUsers,
                'dailyCost' => $nbUsers * $welcomeCredit->getParameterValue()
            ];
        }
        $rides['totalUserCost'] = array_sum(array_column($rides['userStats'], 'dailyCost'));





        if (!$rides) {
            return new JsonResponse(['error' => true, 'message' => 'Aucune donnée trouvée'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['success' => true, 'data' => $rides], Response::HTTP_OK);
    }



}