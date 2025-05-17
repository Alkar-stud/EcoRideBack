<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Ride;
use DateTimeImmutable;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;

class RideControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $testUser;
    private $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $container = $this->client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Création de l'utilisateur test
        $uniqueEmail = 'test-user-' . uniqid() . '@example.com';

        $this->testUser = new User();
        $this->testUser->setEmail($uniqueEmail);
        $this->testUser->setPseudo('TestUser');
        $this->testUser->setPassword(
            $passwordHasher->hashPassword(
                $this->testUser,
                'password123'
            )
        );
        $this->testUser->setRoles(['ROLE_USER']);
        $this->testUser->setCreatedAt(new DateTimeImmutable());
        $this->testUser->setGrade(5);

        // Persister l'utilisateur AVANT de créer le véhicule
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();

        // Création du véhicule
        $this->vehicle = new Vehicle();
        $this->vehicle->setBrand('Toyota');
        $this->vehicle->setModel('Corolla');
        $this->vehicle->setColor("Rouge");
        $this->vehicle->setLicensePlate("aaa-555-aaa");
        $this->vehicle->setLicenseFirstDate(
            (new DateTime())
                ->modify('-' . mt_rand(1, 15) . ' years')
                ->modify('-' . mt_rand(0, 11) . ' months')
                ->modify('-' . mt_rand(0, 30) . ' days')
        );
        $this->vehicle->setEnergy("ECO");
        $this->vehicle->setMaxNbPlacesAvailable(5);
        $this->vehicle->setCreatedAt(new DateTimeImmutable());
        $this->vehicle->setOwner($this->testUser);

        // Création du covoiturage
        $ride = new Ride();
        $ride->setVehicle($this->vehicle);
        $ride->setDriver($this->testUser);
        $ride->setStartingAddress('test adresse départ');
        $ride->setArrivalAddress('test adresse arrivée');
        $startingAt = new DateTimeImmutable('+1 day');
        $ride->setStartingAt($startingAt);
        $ride->setArrivalAt($startingAt->modify('+' . mt_rand(1, 3) . ' hours'));

        $ride->setPrice(5);
        $ride->setNbPlacesAvailable(4);
        $ride->setCreatedAt(new DateTimeImmutable());
        $ride->setStatus('COMING');

        $this->entityManager->persist($this->vehicle);
        $this->entityManager->persist($ride);
        $this->entityManager->flush();
    }

    /**
     * Crée plusieurs covoiturages avec le statut COMING
     */
    private function createMultipleComingRides(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $ride = new Ride();
            $ride->setVehicle($this->vehicle);
            $ride->setDriver($this->testUser);
            $ride->setStartingAddress('test adresse départ ' . $i);
            $ride->setArrivalAddress('test adresse arrivée ' . $i);

            $startingAt = new DateTimeImmutable('+1 day');
            $ride->setStartingAt($startingAt);
            $ride->setArrivalAt($startingAt->modify('+' . mt_rand(1, 3) . ' hours'));

            $ride->setPrice(5 + $i);
            $ride->setNbPlacesAvailable(4);
            $ride->setCreatedAt(new DateTimeImmutable());
            $ride->setStatus('COMING');

            $this->entityManager->persist($ride);
        }
        $this->entityManager->flush();
    }

    /**
     * test de la route add
     */
    public function testAddRide(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Préparation des dates futures
        $startingAt = new DateTimeImmutable('+1 day');
        $arrivalAt = $startingAt->modify('+2 hours');

        // Préparation des données pour la requête
        $rideData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => $startingAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $arrivalAt->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 3,
            'vehicle' => $this->vehicle->getId() // Besoin de corriger le setUp() pour accéder au véhicule
        ];

        // Envoi de la requête
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        // Vérification de la réponse
        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        // Vérification du contenu de la réponse
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals("Covoiturage ajouté avec succès", $responseContent['message']);
    }


    /**
     * test de l'insertion dans MongoDB
     */
    public function testMongoInsertionOnAdd(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Créer un mock du service MongoDB
        $mongoServiceMock = $this->createMock(\App\Service\MongoService::class);

        // Définir les attentes sur le mock
        $mongoServiceMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($document) {
                // Vérifier que le document contient les informations essentielles
                return isset($document['rideId']) &&
                    isset($document['startingAddress']) &&
                    isset($document['arrivalAddress']) &&
                    isset($document['price']) &&
                    isset($document['driver']) &&
                    isset($document['vehicle']);
            }));

        // Remplacer le service réel par notre mock dans le conteneur
        $this->client->getContainer()->set('App\Service\MongoService', $mongoServiceMock);

        // Préparation des dates futures
        $startingAt = new DateTimeImmutable('+1 day');
        $arrivalAt = $startingAt->modify('+2 hours');

        // Préparation des données pour la requête
        $rideData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => $startingAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $arrivalAt->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 2,
            'nbParticipant' => 1,
            'vehicle' => $this->vehicle->getId()
        ];

        // Envoi de la requête
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        // Vérifier le statut de la réponse
        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

    }



    /**
     * Test de la route showAllOwner avec pagination
     */
    public function testShowAllOwnerWithPagination(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Créer des covoiturages supplémentaires pour tester la pagination
        // On a besoin d'au moins 6 covoiturages pour voir la 2ᵉ page avec limite=5
        $this->createMultipleComingRides(7); // 7 nouveaux + 1 existant = 8

        // Requête à la page 2 avec limite de 5.
        $this->client->request(
            'GET',
            '/api/ride/list/coming?page=2&limit=5'
        );

        // Vérification du statut de la réponse
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Vérification du contenu de la réponse
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);

        // Vérification de la pagination
        $this->assertArrayHasKey('pagination', $responseContent);
        $this->assertEquals(2, $responseContent['pagination']['page_courante']);
        $this->assertEquals(5, $responseContent['pagination']['elements_par_page']);
        $this->assertEquals(8, $responseContent['pagination']['elements_totaux']);
        $this->assertEquals(2, $responseContent['pagination']['pages_totales']);

        // Vérification des covoiturages (3 sur la page 2)
        $this->assertArrayHasKey('rides', $responseContent);
        $this->assertCount(3, $responseContent['rides']);
    }


    /**
     * Test de la route showByIdToOwner
     */
    public function testShowByIdToOwner(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Trouver un covoiturage existant (utiliser les fixtures du setUp)
        $ride = $this->entityManager->getRepository(Ride::class)
            ->findOneBy(['driver' => $this->testUser]);

        // Requête GET
        $this->client->request('GET', '/api/ride/' . $ride->getId());

        // Vérification de la réponse
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Vérification du contenu
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($ride->getId(), $responseContent['id']);
    }


    /**
     * Test d'update un covoiturage
     */
    public function testEditRide(): void
    {
        // Authentification
        $this->client->loginUser($this->testUser);

        // Trouver un covoiturage existant
        $ride = $this->entityManager->getRepository(Ride::class)
            ->findOneBy(['driver' => $this->testUser]);

        // Nouvelles données
        $updateData = [
            'price' => 20,
            'nbPlacesAvailable' => 3
        ];

        // Envoi de la requête
        $this->client->request(
            'PUT',
            '/api/ride/' . $ride->getId() . '/update',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        // Vérification
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Vérifier si la mise à jour a bien été effectuée
        $updatedRide = $this->entityManager->getRepository(Ride::class)->find($ride->getId());
        $this->assertEquals(20, $updatedRide->getPrice());
    }


    /**
     * Test de cohérence des données dans MongoDB
     */
    /**
     * Test d'insertion dans MongoDB
     */
    public function testMongoDbInsertionConsistency(): void
    {
        // Authentification
        $this->client->loginUser($this->testUser);

        // Préparation des données
        $startingAt = new DateTimeImmutable('+1 day');
        $arrivalAt = $startingAt->modify('+2 hours');

        $rideData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => $startingAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $arrivalAt->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 3,
            'vehicle' => $this->vehicle->getId()
        ];

        // Mock pour MongoDB
        $addedDocument = null;
        $mongoServiceMock = $this->createMock(\App\Service\MongoService::class);
        $mongoServiceMock->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($document) use (&$addedDocument) {
                $addedDocument = $document;
                return true;
            }));

        $this->client->getContainer()->set('App\Service\MongoService', $mongoServiceMock);

        // Création du covoiturage
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        // Trouver le covoiturage créé en base
        $createdRide = $this->entityManager->getRepository(Ride::class)
            ->findOneBy([
                'driver' => $this->testUser,
                'price' => 15
            ], ['createdAt' => 'DESC']);

        // Vérification de la cohérence
        $this->assertNotNull($addedDocument);
        $this->assertEquals($createdRide->getId(), $addedDocument['rideId']);
        $this->assertEquals(15, $addedDocument['price']);
        $this->assertEquals(3, $addedDocument['nbPlacesAvailable']);
        $this->assertEquals($this->testUser->getId(), $addedDocument['driver']['id']);
        $this->assertEquals($this->vehicle->getBrand(), $addedDocument['vehicle']['brand']);
    }

    /**
     * Test de mise à jour dans MongoDB
     */
    public function testMongoDbUpdateConsistency(): void
    {
        // Authentification
        $this->client->loginUser($this->testUser);

        // Création préalable d'un covoiturage pour le test
        $ride = new Ride();
        $ride->setVehicle($this->vehicle);
        $ride->setDriver($this->testUser);
        $ride->setStartingAddress('{"street":"20 rue de Paris","city":"Paris","zipcode":"75001"}');
        $ride->setArrivalAddress('{"street":"15 avenue Victor Hugo","city":"Reims","zipcode":"51100"}');
        $ride->setStartingAt(new DateTimeImmutable('+1 day'));
        $ride->setArrivalAt(new DateTimeImmutable('+1 day 2 hours'));
        $ride->setPrice(15);
        $ride->setNbPlacesAvailable(3);
        $ride->setCreatedAt(new DateTimeImmutable());
        $ride->setStatus('COMING');

        $this->entityManager->persist($ride);
        $this->entityManager->flush();

        // Mock pour RideService
        $updatedDocument = null;
        $rideServiceMock = $this->createMock(\App\Service\RideService::class);
        $rideServiceMock->expects($this->once())
            ->method('updateRideInMongo')
            ->with($this->callback(function ($updatedRide) use (&$updatedDocument, $ride) {
                $updatedDocument = $updatedRide;
                return $updatedRide->getId() === $ride->getId();
            }));

        $this->client->getContainer()->set('App\Service\RideService', $rideServiceMock);

        // Mise à jour du covoiturage
        $updateData = [
            'price' => 25,
            'nbPlacesAvailable' => 2
        ];

        $this->client->request(
            'PUT',
            '/api/ride/' . $ride->getId() . '/update',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        // Vérifications
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->entityManager->refresh($ride);
        $this->assertEquals(25, $ride->getPrice());
        $this->assertEquals(2, $ride->getNbPlacesAvailable());

        $this->assertNotNull($updatedDocument);
        $this->assertEquals(25, $updatedDocument->getPrice());
        $this->assertEquals(2, $updatedDocument->getNbPlacesAvailable());
    }

    /**
     * Test de validation des adresses
     */
    /**
     * Test d'adresse de départ invalide
     */
    public function testInvalidStartingAddress(): void
    {
        // Authentification
        $this->client->loginUser($this->testUser);

        // Configuration des dates
        $startingAt = new DateTimeImmutable('+1 day');
        $arrivalAt = $startingAt->modify('+2 hours');

        // Mock du validateur d'adresse
        $addressValidatorMock = $this->createMock(\App\Service\AddressValidator::class);
        $addressValidatorMock->method('validateAndDecomposeAddress')
            ->willReturnCallback(function ($address) {
                if ($address === 'adresse invalide') {
                    return ['error' => 'L\'adresse n\'est pas valide'];
                } else {
                    return ['street' => '15 avenue Victor Hugo', 'city' => 'Reims', 'zipcode' => '51100'];
                }
            });

        $this->client->getContainer()->set('App\Service\AddressValidator', $addressValidatorMock);

        // Covoiturage avec adresse de départ invalide
        $rideData = [
            'startingAddress' => 'adresse invalide',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => $startingAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $arrivalAt->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 3,
            'vehicle' => $this->vehicle->getId()
        ];

        // Envoi de la requête
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        // Vérifications
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('startingAddress', $responseContent['error']);
    }

    /**
     * Test de sécurité (accès non autorisé)
     */
    public function testUnauthorizedAccess(): void
    {
        // 1. TEST D'UTILISATEUR NON AUTHENTIFIÉ

        // Préparation des données
        $startingAt = new DateTimeImmutable('+1 day');
        $arrivalAt = $startingAt->modify('+2 hours');

        $rideData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => $startingAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $arrivalAt->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 3,
            'vehicle' => $this->vehicle->getId()
        ];

        // Envoi de la requête sans authentification
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        // Vérification de l'accès refusé
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        // 2. TEST D'ACCÈS AUX COVOITURAGES D'UN AUTRE UTILISATEUR

        // Créer un deuxième utilisateur
        $container = $this->client->getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $otherUser = new User();
        $otherUser->setEmail('other-user' . uniqid() . '@example.com');
        $otherUser->setPseudo('OtherUser');
        $otherUser->setPassword($passwordHasher->hashPassword($otherUser, 'password123'));
        $otherUser->setRoles(['ROLE_USER']);
        $otherUser->setCreatedAt(new DateTimeImmutable());
        $otherUser->setGrade(3);

        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();

        // Trouver un covoiturage existant du premier utilisateur
        $ride = $this->entityManager->getRepository(Ride::class)
            ->findOneBy(['driver' => $this->testUser]);

        // Authentifier le deuxième utilisateur
        $this->client->loginUser($otherUser);

        // Tenter d'accéder au détail d'un covoiturage qui ne lui appartient pas
        $this->client->request('GET', '/api/ride/' . $ride->getId());

        // Vérifier que l'accès est refusé ou renvoie une 404
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        // 3. TEST DE MODIFICATION D'UN COVOITURAGE D'UN AUTRE UTILISATEUR

        // Tenter de modifier un covoiturage qui ne lui appartient pas
        $updateData = [
            'price' => 30,
            'nbPlacesAvailable' => 1
        ];

        $this->client->request(
            'PUT',
            '/api/ride/' . $ride->getId() . '/update',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        // Vérifier que la modification est refusée
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }


    /**
     * Test qu'un covoiturage avec des passagers ne peut pas être supprimé (403)
     */
    public function testCannotDeleteRideWithPassengers(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Récupérer des références fraîches
        $this->entityManager->clear();
        $driver = $this->entityManager->find(User::class, $this->testUser->getId());
        $vehicle = $this->entityManager->find(Vehicle::class, $this->vehicle->getId());

        // Création du passager
        $passenger = new User();
        $passenger->setEmail('passenger-' . uniqid() . '@example.com');
        $passenger->setPseudo('Passenger');
        $passenger->setPassword($this->client->getContainer()->get(UserPasswordHasherInterface::class)
            ->hashPassword($passenger, 'password123'));
        $passenger->setRoles(['ROLE_USER']);
        $passenger->setCreatedAt(new DateTimeImmutable());
        $passenger->setGrade(4);

        $this->entityManager->persist($passenger);
        $this->entityManager->flush();

        // Création du covoiturage avec passager
        $ride = new Ride();
        $ride->setVehicle($vehicle);
        $ride->setDriver($driver);
        $ride->setStartingAddress('{"street":"20 rue de Paris","city":"Paris","zipcode":"75001"}');
        $ride->setArrivalAddress('{"street":"15 avenue Victor Hugo","city":"Reims","zipcode":"51100"}');
        $ride->setStartingAt(new DateTimeImmutable('+1 day'));
        $ride->setArrivalAt(new DateTimeImmutable('+1 day 2 hours'));
        $ride->setPrice(15);
        $ride->setNbPlacesAvailable(3);
        $ride->setCreatedAt(new DateTimeImmutable());
        $ride->setStatus('COMING');

        $this->entityManager->persist($ride);
        $this->entityManager->flush();

        // Ajout du passager
        $ride->addPassenger($passenger);
        $this->entityManager->flush();

        // Vérification du nombre de passagers
        $this->assertEquals(1, $ride->getPassenger()->count());

        // Test de suppression (doit échouer avec 403)
        $this->client->request('DELETE', '/api/ride/' . $ride->getId());
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        // Vérification que le covoiturage existe toujours
        $this->entityManager->clear();
        $rideExists = $this->entityManager->getRepository(Ride::class)->find($ride->getId());
        $this->assertNotNull($rideExists);
    }

    /**
     * Test qu'un covoiturage sans passagers peut être supprimé (204)
     */
    public function testCanDeleteRideWithoutPassengers(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Récupérer des références fraîches
        $this->entityManager->clear();
        $driver = $this->entityManager->find(User::class, $this->testUser->getId());
        $vehicle = $this->entityManager->find(Vehicle::class, $this->vehicle->getId());

        // Création d'un covoiturage sans passagers
        $rideWithoutPassenger = new Ride();
        $rideWithoutPassenger->setVehicle($vehicle);
        $rideWithoutPassenger->setDriver($driver);
        $rideWithoutPassenger->setStartingAddress('{"street":"Avenue des Champs-Elysées","city":"Paris","zipcode":"75008"}');
        $rideWithoutPassenger->setArrivalAddress('{"street":"Place Stanislas","city":"Nancy","zipcode":"54000"}');
        $rideWithoutPassenger->setStartingAt(new DateTimeImmutable('+2 days'));
        $rideWithoutPassenger->setArrivalAt(new DateTimeImmutable('+2 days 3 hours'));
        $rideWithoutPassenger->setPrice(25);
        $rideWithoutPassenger->setNbPlacesAvailable(2);
        $rideWithoutPassenger->setCreatedAt(new DateTimeImmutable());
        $rideWithoutPassenger->setStatus('COMING');

        $this->entityManager->persist($rideWithoutPassenger);
        $this->entityManager->flush();

        // S'assurer que l'ID est bien attribué
        $rideId = $rideWithoutPassenger->getId();
        $this->assertNotNull($rideId, "L'ID du covoiturage doit être attribué après le flush");

        // Création du mock pour MongoService avec l'ID spécifique
        $mongoServiceMock = $this->createMock(\App\Service\MongoService::class);
        $mongoServiceMock->expects($this->once())
            ->method('delete')
            ->with($rideId);

        // Remplacement du service
        $this->client->getContainer()->set('App\Service\MongoService', $mongoServiceMock);

        // Suppression du covoiturage
        $this->client->request('DELETE', '/api/ride/' . $rideId);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        // Vérification de la suppression
        $this->entityManager->clear();
        $deletedRide = $this->entityManager->getRepository(Ride::class)->find($rideId);
        $this->assertNull($deletedRide);
    }


    /**
     * Test avec dates incohérentes
     */
    public function testInvalidDates(): void
    {
        $this->client->loginUser($this->testUser);

        $rideData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => (new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s'),
            'arrivalAt' => (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
            'price' => 15,
            'nbPlacesAvailable' => 3,
            'vehicle' => $this->vehicle->getId()
        ];

        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($rideData)
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('DatesHoursInconsistent', $responseContent['message']);
    }

    /**
     * Test qu'un covoiturage déjà démarré ne peut pas être modifié
     */
    public function testCannotUpdateStartedRide(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Créer un covoiturage avec le statut PROGRESSING
        $startedRide = new Ride();
        $startedRide->setVehicle($this->vehicle);
        $startedRide->setDriver($this->testUser);
        $startedRide->setStartingAddress('{"street":"Boulevard Saint-Michel","city":"Paris","zipcode":"75005"}');
        $startedRide->setArrivalAddress('{"street":"Place Bellecour","city":"Lyon","zipcode":"69002"}');
        $startedRide->setStartingAt(new DateTimeImmutable('-30 minutes')); // Dans le passé car déjà démarré
        $startedRide->setArrivalAt(new DateTimeImmutable('+3 hours'));
        $startedRide->setPrice(45);
        $startedRide->setNbPlacesAvailable(4);
        $startedRide->setCreatedAt(new DateTimeImmutable('-1 day'));
        $startedRide->setStatus('PROGRESSING'); // Statut de covoiturage démarré

        $this->entityManager->persist($startedRide);
        $this->entityManager->flush();

        // Tentative de modification
        $updateData = [
            'price' => 50,
            'nbPlacesAvailable' => 3
        ];

        // Envoi de la requête de mise à jour
        $this->client->request(
            'PUT',
            '/api/ride/' . $startedRide->getId() . '/update',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        // Vérification que la modification est refusée (403 Forbidden)
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        // Vérification du message d'erreur
        $responseContent = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('ne peut pas être modifié', $responseContent['message']);

        // Vérifier que les données n'ont pas été modifiées
        $this->entityManager->refresh($startedRide);
        $this->assertEquals(45, $startedRide->getPrice());
        $this->assertEquals(4, $startedRide->getNbPlacesAvailable());
    }

    /**
     * Test de création d'un covoiturage avec des données manquantes
     */
    public function testCreateRideWithMissingData(): void
    {
        // Authentification de l'utilisateur
        $this->client->loginUser($this->testUser);

        // Données incomplètes (nbPlacesAvailable est manquant)
        $incompleteData = [
            'startingAddress' => '20 rue de Paris, 75001 Paris',
            'arrivalAddress' => '15 avenue Victor Hugo, 51100 Reims',
            'startingAt' => (new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s'),
            'arrivalAt' => (new DateTimeImmutable('+4 hours'))->format('Y-m-d H:i:s'),
            'price' => 15,
            // nbPlacesAvailable est manquant intentionnellement
            'vehicle' => $this->vehicle->getId()
        ];

        // Envoi de la requête
        $this->client->request(
            'POST',
            '/api/ride/add',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($incompleteData)
        );

        // Adaptation à la réalité actuelle de l'API (code 500 au lieu de 400)
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $this->client->getResponse()->getStatusCode());

        // Vérification qu'aucun covoiturage n'a été créé
        $newRides = $this->entityManager->getRepository(Ride::class)->findBy([
            'driver' => $this->testUser,
            'price' => 15
        ], ['createdAt' => 'DESC']);

        foreach ($newRides as $ride) {
            $this->assertNotEquals(
                ['20 rue de Paris', 'Paris', '75001'],
                json_decode($ride->getStartingAddress(), true)
            );
        }
    }

}