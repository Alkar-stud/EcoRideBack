<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250426173502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE notice (id INT AUTO_INCREMENT NOT NULL, published_by_id INT NOT NULL, related_for_id INT NOT NULL, validate_by_id INT DEFAULT NULL, status_id INT NOT NULL, title VARCHAR(50) NOT NULL, content LONGTEXT NOT NULL, grade INT NOT NULL, validate_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_480D45C25B075477 (published_by_id), INDEX IDX_480D45C280F7A1EB (related_for_id), INDEX IDX_480D45C2E52FAB25 (validate_by_id), INDEX IDX_480D45C26BF700BD (status_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C25B075477 FOREIGN KEY (published_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C280F7A1EB FOREIGN KEY (related_for_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C2E52FAB25 FOREIGN KEY (validate_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C26BF700BD FOREIGN KEY (status_id) REFERENCES notice_status (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C25B075477
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C280F7A1EB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C2E52FAB25
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C26BF700BD
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE notice
        SQL);
    }
}
