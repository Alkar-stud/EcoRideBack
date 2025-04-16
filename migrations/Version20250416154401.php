<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250416154401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE covoiturage (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, status_id INT NOT NULL, vehicle_id INT NOT NULL, starting_address VARCHAR(255) NOT NULL, arrival_address VARCHAR(255) NOT NULL, starting_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', trip_duration INT NOT NULL, nb_credit INT NOT NULL, nb_place INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_28C79E897E3C61F9 (owner_id), INDEX IDX_28C79E896BF700BD (status_id), INDEX IDX_28C79E89545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE covoiturage_user (covoiturage_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_F862CC4962671590 (covoiturage_id), INDEX IDX_F862CC49A76ED395 (user_id), PRIMARY KEY(covoiturage_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE covoiturage_status (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(25) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage ADD CONSTRAINT FK_28C79E897E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage ADD CONSTRAINT FK_28C79E896BF700BD FOREIGN KEY (status_id) REFERENCES covoiturage_status (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage ADD CONSTRAINT FK_28C79E89545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage_user ADD CONSTRAINT FK_F862CC4962671590 FOREIGN KEY (covoiturage_id) REFERENCES covoiturage (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage_user ADD CONSTRAINT FK_F862CC49A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE eco_ride
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD grade DOUBLE PRECISION NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE eco_ride (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, parameters LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage DROP FOREIGN KEY FK_28C79E897E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage DROP FOREIGN KEY FK_28C79E896BF700BD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage DROP FOREIGN KEY FK_28C79E89545317D1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage_user DROP FOREIGN KEY FK_F862CC4962671590
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE covoiturage_user DROP FOREIGN KEY FK_F862CC49A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE covoiturage
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE covoiturage_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE covoiturage_status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP grade
        SQL);
    }
}
