<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424193450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE vehicle (id INT AUTO_INCREMENT NOT NULL, energy_id INT NOT NULL, owner_id INT NOT NULL, brand VARCHAR(50) NOT NULL, model VARCHAR(50) NOT NULL, color VARCHAR(50) NOT NULL, registration VARCHAR(20) NOT NULL, registration_first_date DATE NOT NULL, nb_place INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_1B80E486EDDF52D (energy_id), INDEX IDX_1B80E4867E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E486EDDF52D FOREIGN KEY (energy_id) REFERENCES energy (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle ADD CONSTRAINT FK_1B80E4867E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle DROP FOREIGN KEY FK_1B80E486EDDF52D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE vehicle DROP FOREIGN KEY FK_1B80E4867E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE vehicle
        SQL);
    }
}
