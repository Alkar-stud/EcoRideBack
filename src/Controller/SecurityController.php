<?php

namespace App\Controller;

use App\Entity\EcoRide;
use App\Entity\Preferences;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        $this->manager->persist($smokingPreference);
        $this->manager->persist($petsPreference);

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
    public function login(#[CurrentUser] ?User $user): JsonResponse
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
    public function edit(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => true, 'message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
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

}