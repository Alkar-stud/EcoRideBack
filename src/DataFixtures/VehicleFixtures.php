<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Energy;
use App\Entity\Vehicle;
use App\Repository\EnergyRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker;
use Faker\Factory;
use Faker\Provider\FakeCar;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;


class VehicleFixtures extends Fixture
{
    public const VEHICLE_REFERENCE = "Voiture";
    public const VEHICLE_NB_TUPLES = 20;

    public function __construct(
        private readonly EnergyRepository       $repositoryEnergy,
        private readonly UserRepository       $repositoryUser,
        private readonly SerializerInterface    $serializer,
        private readonly UserPasswordHasherInterface $hasher,
    )
    {

    }
    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {

        $faker = (new \Faker\Factory())::create('fr_FR');
        $faker->addProvider(new \Faker\Provider\FakeCar($faker));

        $energy = new Energy();
        $energy->setLibelle($faker->vehicleFuelType());
        $energy->setIsEco(false);
        $energy->setCreatedAt(DateTimeImmutable::createFromMutable($faker->dateTime()));
        $manager->persist($energy);

        $user = new User();
        $user->setPseudo($faker->firstName());
        $user->setEmail($faker->email());
        $user->setPassword($this->hasher->hashPassword($user, $faker->password()));
        $user->setCredits(20);
        $user->setCreatedAt(DateTimeImmutable::createFromMutable($faker->dateTime()));
        $manager->persist($user);

        $this->setEnergy($energy,$user,$manager);

        $manager->flush();
    }

    private function setEnergy(Energy $energy, User $user, ObjectManager $manager): void
    {


        $faker = (new Factory())::create('fr_FR');
        $faker->addProvider(new FakeCar($faker));

        for ($i = 1; $i <= self::VEHICLE_NB_TUPLES; $i++) {

            $vehicle = (new Vehicle())
                ->setBrand($faker->vehicleBrand())
                ->setModel($faker->vehicleModel())
                ->setColor($faker->colorName())
                ->setRegistration($faker->vehicleRegistration('[A-Z]{2}-[0-9]{3}-[A-Z]{2}'))
                ->setRegistrationFirstDate(new DateTimeImmutable($faker->date('Y-m-d')))
                ->setNbPlace(random_int(1,5))
                ->setEnergy($energy)
                ->setOwner($user)
                ->setCreatedAt(DateTimeImmutable::createFromMutable($faker->dateTime()));

            $manager->persist($vehicle);
            $this->addReference(self::VEHICLE_REFERENCE . $i, $vehicle);
        }

    }
}
