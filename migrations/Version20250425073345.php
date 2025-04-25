<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250425073345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE trip (id INT AUTO_INCREMENT NOT NULL, status_id INT NOT NULL, owner_id INT NOT NULL, vehicle_id INT NOT NULL, starting_address VARCHAR(255) NOT NULL, arrival_address VARCHAR(255) NOT NULL, starting_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', duration INT NOT NULL, nb_credit INT NOT NULL, nb_place_remaining INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7656F53B6BF700BD (status_id), INDEX IDX_7656F53B7E3C61F9 (owner_id), INDEX IDX_7656F53B545317D1 (vehicle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE trip_user (trip_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_A6AB4522A5BC2E0E (trip_id), INDEX IDX_A6AB4522A76ED395 (user_id), PRIMARY KEY(trip_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip ADD CONSTRAINT FK_7656F53B6BF700BD FOREIGN KEY (status_id) REFERENCES trip_status (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip ADD CONSTRAINT FK_7656F53B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip ADD CONSTRAINT FK_7656F53B545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicle (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip_user ADD CONSTRAINT FK_A6AB4522A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip_user ADD CONSTRAINT FK_A6AB4522A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B6BF700BD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B7E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B545317D1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip_user DROP FOREIGN KEY FK_A6AB4522A5BC2E0E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip_user DROP FOREIGN KEY FK_A6AB4522A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE trip
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE trip_user
        SQL);
    }
}
