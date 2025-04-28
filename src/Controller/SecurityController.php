<?php

namespace App\Controller;

use App\Entity\EcoRide;
use App\Entity\Preferences;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name: 'app_api_')]
final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly SerializerInterface    $serializer
    )
    {
    }

    #[Route('/registration', name: 'registration', methods: 'POST')]
    #[OA\Tag(name: 'User')]
    #[OA\Post(
        path:"/api/registration",
        summary:"Inscription d'un nouvel utilisateur",
        requestBody :new RequestBody(
            description: "Données de l'utilisateur à inscrire",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                        property: "pseudo",
                        type: "string",
                        example: "Pseudo"
                    ),
                    new Property(
                        property: "email",
                        type: "string",
                        example: "adresse@email.com"
                    ),
                    new Property(
                        property: "password",
                        type: "string",
                        example: "Mot de passe"
                    )], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur inscrit avec succès',
        content: new Model(type: User::class, groups: ['user_login'])
    )]
    #[OA\Response(
        response: 400,
        description: 'Mot de passe pas assez complexe : au moins 1 "long", "uppercase", "lowercase", "digit" ou "special" character ou il manque le pseudo'
    )]
    #[OA\Response(
        response: 409,
        description: 'Ce compte existe déjà'
    )]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

        //Vérification de l'existence de l'utilisateur pour ne pas avoir d'email en double
        $existingUser = $this->manager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return new JsonResponse(['error' => true, 'message' => 'Ce compte existe déjà'], Response::HTTP_CONFLICT);
        }

        if (null === $user->getPseudo()) {
            return new JsonResponse(['error' => true, 'message' => 'Il manque le pseudo'], Response::HTTP_BAD_REQUEST);
        }

        if (null === $user->getPassword()) {
            return new JsonResponse(['error' => true, 'message' => 'Il faut un mot de passe !'], Response::HTTP_BAD_REQUEST);
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $user->getPassword())) {
            return new JsonResponse(['message' => 'Le mot de passe doit contenir au moins 10 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial.'], Response::HTTP_BAD_REQUEST);
        }


        // Recherche de l'entité EcoRide avec le libelle "WELCOME_CREDIT"
        $ecoRide = $this->manager->getRepository(EcoRide::class)->findOneBy(['libelle' => 'WELCOME_CREDIT']);
        // Vérification si l'entité existe et récupération de la valeur des crédits
        $welcomeCredit = $ecoRide ? (int) $ecoRide->getParameters() : 0;
        // Attribution des crédits à l'utilisateur
        $user->setCredits($welcomeCredit);

        //aucun roles ne peut être attribué à la création
        $user->setRoles([]);

        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));

        //On ajoute 2 préférences 'smokingAllowed' et 'petsAllowed' avec en description 'no'
        $smokingPreference = new Preferences();
        $smokingPreference->setLibelle('smokingAllowed');
        $smokingPreference->setDescription('no');
        $smokingPreference->setUser($user);
        $smokingPreference->setCreatedAt(new DateTimeImmutable());

        $petsPreference = new Preferences();
        $petsPreference->setLibelle('petsAllowed');
        $petsPreference->setDescription('no');
        $petsPreference->setUser($user);
        $petsPreference->setCreatedAt(new DateTimeImmutable());
        //Création des préférences pour le user
        $this->manager->persist($smokingPreference);
        $this->manager->persist($petsPreference);

        //Association des préférences au user
        $user->addPreference($smokingPreference);
        $user->addPreference($petsPreference);

        $user->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($user);
        $this->manager->flush();

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_login']]
        );

        return new JsonResponse($responseData, Response::HTTP_CREATED, [], true);
    }

    #[Route('/login', name: 'login', methods: 'POST')]
    #[OA\Tag(name: 'User')]
    #[OA\Post(
        path:"/api/login",
        summary:"Connecter un utilisateur",
        requestBody :new RequestBody(
            description: "Données de l’utilisateur pour se connecter",
            required: true,
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                    property: "email",
                    type: "string",
                    example: "adresse@email.com"
                ),
                    new Property(
                        property: "password",
                        type: "string",
                        example: "Mot de passe"
                    )], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: "Connexion réussie",
        content: new Model(type: User::class, groups: ['user_login'])
    )]
    public function login(#[CurrentUser] ?User $user): JsonResponse | RedirectResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => true, 'message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }
        //Si le user n'a pas le droit de se connecter
        if (!$user->isActive()) {
            return new JsonResponse(['error' => true, 'message' => 'Votre compte est désactivé. Veuillez nous contacter pour plus d\'informations'], Response::HTTP_FORBIDDEN);
        }

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_login']]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account/me', name: 'account_me', methods: 'GET')]
    #[OA\Tag(name: 'User')]
    #[OA\Get(
        path:"/api/account/me",
        summary:"Récupérer toutes les informations du User connecté",
    )]
    #[OA\Response(
        response: 200,
        description: 'User trouvé avec succès',
        content: new Model(type: User::class, groups: ['user_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => true, 'message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_read']]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account/edit', name: 'account_edit', methods: 'PUT')]
    #[OA\Tag(name: 'User')]
    #[OA\Put(
        path:"/api/account/edit",
        summary:"Modifier son compte utilisateur",
        requestBody :new RequestBody(
            description: "Données de l'utilisateur à modifier",
            content: [new MediaType(mediaType:"application/json",
                schema: new Schema(properties: [new Property(
                        property: "pseudo",
                        type: "string",
                        example: "Nouveau pseudo"
                    ),
                    new Property(
                        property: "photo",
                        type: "string",
                        example: "Nouvelle photo"
                    ),
                    new Property(
                        property: "isDriver",
                        type: "boolean",
                        example: true
                    ),
                    new Property(
                        property: "isPassenger",
                        type: "boolean",
                        example: true
                    ),
                ], type: "object"))]
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'User modifié avec succès',
        content: new Model(type: User::class, groups: ['user_read'])
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function edit(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $originalEmail = $user->getEmail();
        $originalRoles = $user->getRoles();
        $originalIsActive = $user->isActive();
        $originalCredits = $user->getCredits();

        $user = $this->serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );
        // Empêcher la modification des rôles par le User
        $user->setRoles($originalRoles);
        // Empêcher la modification de isActive par le User
        $user->setIsActive($originalIsActive);
        // Empêcher la modification de l'email par le User
        $user->setEmail($originalEmail);

        $user->setUpdatedAt(new DateTimeImmutable());

        // Vérifier si l'utilisateur a le rôle ROLE_ADD_CREDIT avant de modifier les crédits
        if (!$this->isGranted('ROLE_ADD_CREDIT')) {
            $user->setCredits($originalCredits);
        }
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            ['groups' => ['user_read']]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account', name: 'account_delete', methods: 'DELETE')]
    #[OA\Tag(name: 'User')]
    #[OA\Delete(
        path:"/api/account",
        summary:"Supprimer son compte utilisateur",
    )]
    #[OA\Response(
        response: 204,
        description: 'User supprimé avec succès'
    )]
    #[OA\Response(
        response: 404,
        description: 'User non trouvé'
    )]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user) {
            $this->manager->remove($user);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

}