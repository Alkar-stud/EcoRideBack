<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'app_api_')]
final class SecurityController extends AbstractController
{
    private const MESSAGE_MISSING_CREDENTIALS = 'Missing credentials';
    private const DEFAULT_VALUE_CREDITS = 0;

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

        $email = $user->getEmail();
        $existingUser = $this->manager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            return new JsonResponse(['message' => 'Email already exists'], Response::HTTP_CONFLICT);
        }

        if (null === $user->getPseudo()) {
            return new JsonResponse(['message' => 'Missing pseudo'], Response::HTTP_BAD_REQUEST);
        }

        if (null === $user->getPassword()) {
            return new JsonResponse(['message' => 'Missing password'], Response::HTTP_BAD_REQUEST);
        }

        //On met les crédits à DEFAULT_VALUE_CREDITS par défaut
        $user->setCredits(self::DEFAULT_VALUE_CREDITS);


        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));
        $user->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(
            ['user'  => $user->getUserIdentifier(), 'pseudo'  => $user->getPseudo(), 'apiToken' => $user->getApiToken(), 'roles' => $user->getRoles()],
            Response::HTTP_CREATED
        );
    }

    #[Route('/login', name: 'login', methods: 'POST')]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => self::MESSAGE_MISSING_CREDENTIALS], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user'  => $user->getUserIdentifier(),
            'pseudo'  => $user->getPseudo(),
            'apiToken' => $user->getApiToken()
        ]);
    }

    #[Route('/account/me', name: 'account_me', methods: 'GET')]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => self::MESSAGE_MISSING_CREDENTIALS], Response::HTTP_UNAUTHORIZED);
        }

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['password']
            ]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    #[Route('/account/edit', name: 'account_edit', methods: 'PUT')]
    public function edit(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => self::MESSAGE_MISSING_CREDENTIALS], Response::HTTP_UNAUTHORIZED);
        }

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

        $user->setUpdatedAt(new DateTimeImmutable());

        // Vérifier si l'utilisateur a le rôle ROLE_ADD_CREDIT avant de modifier les crédits
        if (!$this->isGranted('ROLE_ADD_CREDIT')) {
            $user->setCredits($originalCredits);
        }

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN avant de modifier isActive
        if (!$this->isGranted('ROLE_ADMIN')) {
            $user->setIsActive($originalIsActive);
        }

        $this->manager->flush();

        $responseData = $this->serializer->serialize(
            $user,
            'json',
            [
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['password']
            ]
        );

        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

}
