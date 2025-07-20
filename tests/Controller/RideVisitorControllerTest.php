<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Ride;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;

class RideVisitorControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private ?object $entityManager;
    private User $testUser;
    private $ride;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $container = $this->client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->testUser = $this->createUser('visitor-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($this->testUser);
        $this->ride = $this->createRide($vehicle, $this->testUser);
    }

    public function testAddAndRemovePassenger(): void
    {
        // Création d'un conducteur et d'un passager distincts
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver);

        // Connexion en tant que passager
        $this->client->loginUser($passenger);
        $apiToken = $this->generateApiToken($passenger);
        $headers = ['HTTP_X-AUTH-TOKEN' => $apiToken];

        $this->updateRidePassenger($ride->getId(), '/addUser', 'ajouté', $headers);
        $this->updateRidePassenger($ride->getId(), '/removeUser', 'retiré', $headers);
    }

    public function testStartRideWithPassenger(): void
    {
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver, 'COMING');
        $ride->addPassenger($passenger);
        $this->entityManager->flush();

        $apiToken = $driver->getApiToken();
        $headers = ['HTTP_X-AUTH-TOKEN' => $apiToken];

        $this->client->request(
            'PUT',
            '/api/ride/' . $ride->getId() . '/start',
            [],
            [],
            $headers
        );

        // Output the response content for debugging
        $response = $this->client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('En Cours', $responseData['message']);

        $this->entityManager->refresh($ride);
        $this->assertEquals('PROGRESSING', $ride->getStatus());
    }


    public function testStopRideWithPassenger(): void
    {
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver, 'PROGRESSING');
        $ride->addPassenger($passenger);
        $this->entityManager->flush();

        $this->updateRideStatus($ride->getId(), $driver->getApiToken(), '/stop', 'statut', 'VALIDATIONPROCESSING');
    }

    public function testAddValidationAndNotice(): void
    {
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver, 'VALIDATIONPROCESSING');
        $ride->addPassenger($passenger);
        $this->entityManager->flush();

        $apiToken = $passenger->getApiToken();
        $headers = [
            'HTTP_X-AUTH-TOKEN' => $apiToken,
            'CONTENT_TYPE' => 'application/json'
        ];

        $this->addValidation($ride->getId(), $headers, true);
        $this->addNotice($ride->getId(), $headers, true);
    }

    public function testAddValidationAllIsNoOkAndNotice(): void
    {
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver, 'VALIDATIONPROCESSING');
        $ride->addPassenger($passenger);
        $this->entityManager->flush();

        $apiToken = $passenger->getApiToken();
        $headers = [
            'HTTP_X-AUTH-TOKEN' => $apiToken,
            'CONTENT_TYPE' => 'application/json'
        ];

        $this->addValidation($ride->getId(), $headers, false);
        $this->addNotice($ride->getId(), $headers, false);
    }

    private function createUser($prefix): User
    {
        $uniqueId = uniqid(); // Generate a unique ID once per user
        $user = new User();
        $user->setEmail($prefix); // Use the unique ID once
        $user->setPseudo('User' . $uniqueId);
        $user->setPassword(
            $this->client->getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($user, 'password123')
        );
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setGrade(5);
        $user->setCredits(500);
        $user->setApiToken(bin2hex(random_bytes(16)));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }


    private function createVehicle(User $owner): Vehicle
    {
        $vehicle = new Vehicle();
        $vehicle->setBrand('Renault');
        $vehicle->setModel('Clio');
        $vehicle->setColor('Bleu');
        $vehicle->setLicensePlate('ZZ-999-ZZ');
        $vehicle->setLicenseFirstDate(new \DateTime('-5 years'));
        $vehicle->setEnergy('ECO');
        $vehicle->setMaxNbPlacesAvailable(4);
        $vehicle->setCreatedAt(new DateTimeImmutable());
        $vehicle->setOwner($owner);
        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();
        return $vehicle;
    }

    private function createRide(Vehicle $vehicle, User $driver, string $status = 'COMING'): Ride
    {
        $ride = new Ride();
        $ride->setVehicle($vehicle);
        $ride->setDriver($driver);
        $ride->setStartingStreet('Rue A');
        $ride->setStartingPostCode('75000');
        $ride->setStartingCity('Paris');
        $ride->setArrivalStreet('Rue B');
        $ride->setArrivalPostCode('69000');
        $ride->setArrivalCity('Lyon');
        $ride->setStartingAt(new DateTimeImmutable('-1 hour'));
        $ride->setArrivalAt(new DateTimeImmutable('+ 3 hours'));
        $ride->setPrice(10);
        $ride->setNbPlacesAvailable(3);
        $ride->setCreatedAt(new DateTimeImmutable());
        $ride->setStatus($status);
        $this->entityManager->persist($ride);
        $this->entityManager->flush();
        return $ride;
    }

    private function createEcoRide(string $libelle, int $parameterValue): \App\Entity\Ecoride
    {
        $ecoRide = new \App\Entity\Ecoride();
        $ecoRide->setLibelle($libelle);
        $ecoRide->setParameterValue($parameterValue);
        $ecoRide->setCreatedAt(new DateTimeImmutable());
        $ecoRide->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->persist($ecoRide);
        $this->entityManager->flush();
        return $ecoRide;
    }

    private function generateApiToken(User $user): string
    {
        $apiToken = bin2hex(random_bytes(32));
        $user->setApiToken($apiToken);
        $this->entityManager->flush();
        return $apiToken;
    }

    private function updateRidePassenger(int $rideId, string $endpoint, string $expectedMessage, array $headers): void
    {
        $this->client->request('PUT', '/api/ride/' . $rideId . $endpoint, [], [], $headers);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString($expectedMessage, $response['message']);
    }

    private function updateRideStatus(int $rideId, string $apiToken, string $endpoint, string $expectedMessage, string $expectedStatus): void
    {
        $this->client->request('PUT', '/api/ride/' . $rideId . $endpoint, [], [], ['HTTP_X-AUTH-TOKEN' => $apiToken]);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString($expectedMessage, $response['message']);

        $this->entityManager->refresh($this->entityManager->getRepository(Ride::class)->find($rideId));
        $this->assertEquals($expectedStatus, $this->entityManager->getRepository(Ride::class)->find($rideId)->getStatus());
    }

    private function addValidation(int $rideId, array $headers, bool $isAllOk): void
    {
        $validationData = [
            'isAllOk' => $isAllOk,
            'content' => $isAllOk ? 'Tout s\'est bien passé' : 'Horrible ! Le chauffeur écoutait de la musique pour chien !'
        ];

        $this->client->request('POST', '/api/validation/add/' . $rideId, [], [], $headers, json_encode($validationData));

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), "Expected HTTP status code 200, but got " . $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('validation', $responseData['message']);
    }


    private function addNotice(int $rideId, array $headers, bool $isAllOk): void
    {
        $noticeDataOk = [
            'grade' => 5,
            'title' => 'Super trajet',
            'content' => 'Chauffeur très sympa, je recommande !'
        ];

        $noticeDataNOk = [
            'grade' => 0,
            'title' => 'Trajet très long',
            'content' => 'Horrible ! Le chauffeur écoutait de la musique pour chien !'
        ];

        if ($isAllOk === true) {
            $noticeDataTest = $noticeDataOk;
        } else {
            $noticeDataTest = $noticeDataNOk;
        }
        $this->client->request('POST', '/api/ride/' . $rideId . '/addNotice', [], [], $headers, json_encode($noticeDataTest));
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Votre avis sera publié', $response[0] ?? $response['message'] ?? '');
    }

    public function testDriverAndPlatformArePaidOnLastValidation(): void
    {
        $driver = $this->createUser('driver-' . uniqid() . '@example.com');
        $passenger1 = $this->createUser('passenger-' . uniqid() . '@example.com');
        $passenger2 = $this->createUser('passenger-' . uniqid() . '@example.com');
        $vehicle = $this->createVehicle($driver);
        $ride = $this->createRide($vehicle, $driver, 'VALIDATIONPROCESSING');
        $ride->addPassenger($passenger1);
        $ride->addPassenger($passenger2);
        $this->entityManager->flush();

        $apiToken1 = $passenger1->getApiToken();
        $apiToken2 = $passenger2->getApiToken();
        $headers1 = [
            'HTTP_X-AUTH-TOKEN' => $apiToken1,
            'CONTENT_TYPE' => 'application/json'
        ];
        $headers2 = [
            'HTTP_X-AUTH-TOKEN' => $apiToken2,
            'CONTENT_TYPE' => 'application/json'
        ];

        // Récupérer les entités Ecoride existantes
        $platformCommission = $this->entityManager->getRepository(\App\Entity\Ecoride::class)
            ->findOneBy(['libelle' => 'PLATFORM_COMMISSION_CREDIT']);
        $ecoRideTotal = $this->entityManager->getRepository(\App\Entity\Ecoride::class)
            ->findOneBy(['libelle' => 'TOTAL_CREDIT']);

        // Si les entités n'existent pas, vous pouvez les créer ici
        if (!$platformCommission) {
            $platformCommission = $this->createEcoRide('PLATFORM_COMMISSION_CREDIT', 3);
            $this->entityManager->flush();
        }
        if (!$ecoRideTotal) {
            $ecoRideTotal = $this->createEcoRide('TOTAL_CREDIT', 0);
            $this->entityManager->flush();
        }

        // Crédit initial du chauffeur
        $initialDriverCredits = $driver->getCredits();

        // 1. Premier passager valide
        $this->addValidation($ride->getId(), $headers1, true);

        // Le chauffeur n'est pas encore payé
        $this->assertEquals($initialDriverCredits, $driver->getCredits());

        // 2. Deuxième (dernier) passager valide
        $this->addValidation($ride->getId(), $headers2, true);

        // La plateforme a reçu la commission
        //On va chercher de nouveau le crédit
        $newEcoRideTotal = $this->entityManager->getRepository(\App\Entity\EcoRide::class)
            ->findOneBy(['libelle' => 'TOTAL_CREDIT']);
        $newEcoRideTotalWaiting = $ecoRideTotal->getParameterValue() + $platformCommission->getParameterValue();
        $this->assertEquals($newEcoRideTotalWaiting, $newEcoRideTotal->getParameterValue());

        //Compte des passagers
        $nbPassengers = count($ride->getPassenger());
        // Le chauffeur a reçu (2 passagers * 10 (RidePrice) ) - 2 (commission) = 18
        $payment = ($nbPassengers * $ride->getPrice());

        //On met à jour le crédit du chauffeur
        $ride->getDriver()->setCredits($ride->getDriver()->getCredits() + $payment - $platformCommission->getParameterValue());
        var_dump($driver->getCredits());
        $this->assertEquals($initialDriverCredits + 18, $driver->getCredits());



    }



}
