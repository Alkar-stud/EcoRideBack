<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250605075059 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO `user` (`email`, `roles`, `password`, `pseudo`, `credits`, `is_driver`, `is_passenger`, `api_token`, `is_active`, `created_at`) VALUES ('admin@ecoride.fr', '["admin"]', '$2y$13$r.iH55Y3TpA3MJKo7DeMpu0n1h1nYEBsBltwWuQTHR1r9rN/.btUS', 'Admin', 0, false, false, '86135ae9-54e8-46a7-997c-f3dd345d8b5d48cada49fd53e8b20070', true, NOW())
        SQL);

    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM user WHERE `user`.`email` = 'admin@ecoride.fr'
        SQL);
    }
}
