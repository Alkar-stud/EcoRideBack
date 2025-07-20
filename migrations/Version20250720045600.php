<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated for existing vehicles
 */
final class Version20250720045600 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO `vehicle` (`id`, `owner_id`, `brand`, `model`, `color`, `license_plate`, `license_first_date`, `energy`, `max_nb_places_available`, `created_at`) VALUES
            (1, 2, 'Aston Martin', 'DB5', 'Grise', 'BMT 216A', '1964-01-02', 'ALMOSTECO', 1, NOW()),
            (2, 2, 'Citroën', '2 CV', 'Bleue', '123 AA 75', '1968-05-02', 'NOTECO', 3, NOW()),
            (3, 2, 'Ford', 'Gran Torino', 'Rouge et blanche', '537 ONN', '1975-02-02', 'ECO', 3, NOW()),
            (4, 2, 'Renault', 'R4', 'Blanche', '9999 ZZ 75', '1970-01-01', 'ECO', 4, NOW());
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            TRUNCATE `vehicle`;
        SQL);
    }
}
