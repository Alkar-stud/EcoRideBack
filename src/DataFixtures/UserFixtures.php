<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use DateTimeImmutable;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $usersData = [
            2 => [
                'email' => 'ecoridestud+driver@alwaysdata.net',
                'roles' => [],
                'password' => '$2y$13$lxRdn4YV7pmIwSxBXR705usHQkysPzDMOuucjsxHd2vEwGHJVGVUW',
                'pseudo' => 'Driver',
                'photo' => null,
                'credits' => 20,
                'grade' => null,
                'isDriver' => true,
                'isPassenger' => false,
                'apiToken' => '712a5fbf6e0d20f9ded3d16e43e75ca327fd27d72b29d9f79331dea5505c6880',
                'isActive' => true,
            ],
            3 => [
                'email' => 'ecoridestud+passenger@alwaysdata.net',
                'roles' => [],
                'password' => '$2y$13$A7mN8xmw7/JebrAq8wDPkOrj5JUzu0wmnYpt9gB85Gqj1fIt0wqy6',
                'pseudo' => 'Passager',
                'photo' => null,
                'credits' => 1000,
                'grade' => null,
                'isDriver' => false,
                'isPassenger' => true,
                'apiToken' => 'b68c64124987c8a459212956185890fe6c4014c610b72e64c2e4dddc7df6818d',
                'isActive' => true,
            ],
            4 => [
                'email' => 'ecoridestud+both@alwaysdata.net',
                'roles' => [],
                'password' => '$2y$13$HnS0kRmcgppbU8zIm8tY8evDBqg9KUI/aJeaZNdqNk1StJbM2qOy6',
                'pseudo' => 'Both',
                'photo' => null,
                'credits' => 1000,
                'grade' => null,
                'isDriver' => true,
                'isPassenger' => true,
                'apiToken' => '20b70d4134a344ef418beed6927d393536af3f9c58f1d3de5f4ebdf2e12656c5',
                'isActive' => true,
            ],
            5 => [
                'email' => 'ecoridestud+employee@alwaysdata.net',
                'roles' => ['employee'],
                'password' => '$2y$13$LjIjgKZHZz5ACwxW0coxTe/rA8O.NY.zhLdXjlmD5aU7jZquGVCF2',
                'pseudo' => 'Employé',
                'photo' => null,
                'credits' => 0,
                'grade' => null,
                'isDriver' => false,
                'isPassenger' => false,
                'apiToken' => '6ba2e9eca47fa6501d49f3b9e1e7fddc647ddd68dd94842f74cd259b15890818',
                'isActive' => true,
            ],
            6 => [
                'email' => 'ecoridestud+employee2@alwaysdata.net',
                'roles' => ['employee'],
                'password' => '$2y$13$ryfNUSLcO2AMglj3MUxGpO7FlzXXltWdlDjYbLyT2GY6Ec7e.SrPm',
                'pseudo' => 'EmployéInactif',
                'photo' => null,
                'credits' => 0,
                'grade' => null,
                'isDriver' => false,
                'isPassenger' => false,
                'apiToken' => 'd88a55c12da322bfce438004a7f5344e260fb176f8ad60b8b9b16a23cc188a40',
                'isActive' => false,
            ],
        ];

        foreach ($usersData as $id => $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword($data['password']);
            $user->setPseudo($data['pseudo']);
            $user->setPhoto($data['photo']);
            $user->setCredits($data['credits']);
            $user->setGrade($data['grade']);
            $user->setIsDriver($data['isDriver']);
            $user->setIsPassenger($data['isPassenger']);
            $user->setApiToken($data['apiToken']);
            $user->setIsActive($data['isActive']);
            $user->setCreatedAt(new DateTimeImmutable('2025-07-20 07:17:20'));
            $this->addReference('user_' . $id, $user);
            $manager->persist($user);
        }
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['UserFixtures'];
    }
}
