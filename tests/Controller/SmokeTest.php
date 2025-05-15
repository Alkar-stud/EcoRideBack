<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SmokeTest extends WebTestCase
{
    private ?User $testUser = null;
    private ?string $plainPassword = null;
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $container = $this->client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Créer un utilisateur de test
        $this->plainPassword = 'mdp_test_123';
        $this->testUser = new User();
        $this->testUser->setEmail('test_security@example.com');
        $this->testUser->setPseudo('security_test_user');
        $this->testUser->setCreatedAt(new \DateTimeImmutable());

        // Encoder le mot de passe
        $hashedPassword = $passwordHasher->hashPassword(
            $this->testUser,
            $this->plainPassword
        );
        $this->testUser->setPassword($hashedPassword);

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $entityManager->getRepository(User::class)
            ->findOneBy(['email' => $this->testUser->getEmail()]);

        if (!$existingUser) {
            $entityManager->persist($this->testUser);
            $entityManager->flush();
        } else {
            $this->testUser = $existingUser;
        }
    }

    public function testLoginSuccess(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->testUser->getEmail(),
                'password' => $this->plainPassword
            ])
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('apiToken', $content);
    }

    public function testLoginFailureWithWrongPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $this->testUser->getEmail(),
                'password' => 'mauvais_mot_de_passe'
            ])
        );

        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginFailureWithInvalidRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $this->testUser->getEmail()]) // Sans mot de passe
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testProtectedRouteRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/account/me');

        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
    }
}