<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424142720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE eco_ride (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, parameters LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        //On ajoute les paramètres
        $this->addSql(<<<'SQL'
            INSERT INTO `eco_ride` (`libelle`, `parameters`, `created_at`) VALUES ('TOTAL_CREDIT', '0', NOW()), ('WELCOME_CREDIT', '20', NOW()), ('DEFAULT_COVOITURAGE_STATUS_ID', '1', NOW()),('COST_EACH_RIDE', '2', NOW()),('DEFAULT_NOTICE_STATUS_ID', '1', NOW());
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE eco_ride
        SQL);
    }
}
