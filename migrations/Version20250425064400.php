<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250425064400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE trip_status (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(25) NOT NULL, code VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO `trip_status` (`libelle`, `code`, `created_at`) VALUES ('À Venir', 'coming', NOW()), ('En Cours', 'progressing', NOW()), ('En Cours De Validation', 'validationProcess', NOW()), ('Terminé', 'finished', NOW()), ('Annulé', 'canceled', NOW()), ('En Attente De Validation', 'awaitingValidation', NOW())
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE trip_status
        SQL);
    }
}
