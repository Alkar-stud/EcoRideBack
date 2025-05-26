<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250526133357 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE validation (id INT AUTO_INCREMENT NOT NULL, passenger_id INT NOT NULL, ride_id INT NOT NULL, closed_by_id INT DEFAULT NULL, is_all_ok TINYINT(1) NOT NULL, content LONGTEXT DEFAULT NULL, is_closed TINYINT(1) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_16AC5B6E4502E565 (passenger_id), INDEX IDX_16AC5B6E302A8A70 (ride_id), INDEX IDX_16AC5B6EE1FA7797 (closed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation ADD CONSTRAINT FK_16AC5B6E4502E565 FOREIGN KEY (passenger_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation ADD CONSTRAINT FK_16AC5B6E302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation ADD CONSTRAINT FK_16AC5B6EE1FA7797 FOREIGN KEY (closed_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ride CHANGE arrival_city arrival_city VARCHAR(255) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE validation DROP FOREIGN KEY FK_16AC5B6E4502E565
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation DROP FOREIGN KEY FK_16AC5B6E302A8A70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation DROP FOREIGN KEY FK_16AC5B6EE1FA7797
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE validation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE ride CHANGE arrival_city arrival_city VARCHAR(20) NOT NULL
        SQL);
    }
}
