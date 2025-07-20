<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250515115601 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE ecoride (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, parameter_value LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO ecoride (libelle, parameter_value, created_at) VALUES 
                ('START_CREDIT', '20000', NOW()), 
                ('TOTAL_CREDIT', '20000', NOW()), 
                ('WELCOME_CREDIT', '20', NOW()), 
                ('PLATFORM_COMMISSION_CREDIT', '2', NOW()),
                ('DEFAULT_RIDE_STATUS', 'COMING', NOW()), 
                ('FINISHED_RIDE_STATUS', 'FINISHED', NOW());
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE ecoride
        SQL);
    }
}
