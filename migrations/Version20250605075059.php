<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated for existing users
 */
final class Version20250605075059 extends AbstractMigration
{
    /*
     * Pour ajouter users, covoiturages...
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO `user` (`id`, `email`, `roles`, `password`, `pseudo`, `photo`, `credits`, `grade`, `is_driver`, `is_passenger`, `api_token`, `is_active`, `created_at`) VALUES
            (1, 'ecoridestud+admin@alwaysdata.net', '["admin"]', '$2y$13$r.iH55Y3TpA3MJKo7DeMpu0n1h1nYEBsBltwWuQTHR1r9rN/.btUS', 'Admin', NULL, 0, NULL, 0, 0, '86135ae9-54e8-46a7-997c-f3dd345d8b5d48cada49fd53e8b20070', 1, NOW()),
            (2, 'ecoridestud+driver@alwaysdata.net', '[]', '$2y$13$lxRdn4YV7pmIwSxBXR705usHQkysPzDMOuucjsxHd2vEwGHJVGVUW', 'Driver', NULL, 20, NULL, 1, 0, '712a5fbf6e0d20f9ded3d16e43e75ca327fd27d72b29d9f79331dea5505c6880', 1, NOW()),
            (3, 'ecoridestud+passenger@alwaysdata.net', '[]', '$2y$13$A7mN8xmw7/JebrAq8wDPkOrj5JUzu0wmnYpt9gB85Gqj1fIt0wqy6', 'Passager', NULL, 150, NULL, 0, 1, 'b68c64124987c8a459212956185890fe6c4014c610b72e64c2e4dddc7df6818d', 1, NOW()),
            (4, 'ecoridestud+both@alwaysdata.net', '[]', '$2y$13$HnS0kRmcgppbU8zIm8tY8evDBqg9KUI/aJeaZNdqNk1StJbM2qOy6', 'Both', NULL, 150, NULL, 1, 1, '20b70d4134a344ef418beed6927d393536af3f9c58f1d3de5f4ebdf2e12656c5', 1, NOW()),
            (5, 'ecoridestud+employee@alwaysdata.net', '["employee"]', '$2y$13$LjIjgKZHZz5ACwxW0coxTe/rA8O.NY.zhLdXjlmD5aU7jZquGVCF2', 'Employé', NULL, 0, NULL, 0, 0, '6ba2e9eca47fa6501d49f3b9e1e7fddc647ddd68dd94842f74cd259b15890818', 1, NOW()),
            (6, 'ecoridestud+employee2@alwaysdata.net', '["employee"]', '$2y$13$aRZ0WvZZy5xTFphjNwMmvuLqRWb8.k98S0ZIU.5jcUjAaGJo3xLSK', 'EmployéInactif', NULL, 0, NULL, 0, 0, '6ba2e9eca47fa6501d49f3b9e1e7fddc647ddd68dd94842f74cd259b15890817', 0, NOW());
        SQL);

    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            TRUNCATE `user`;
        SQL);
    }
}
