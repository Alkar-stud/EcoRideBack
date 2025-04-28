<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250428072539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C280F7A1EB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C280F7A1EB FOREIGN KEY (related_for_id) REFERENCES trip (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE notice DROP FOREIGN KEY FK_480D45C280F7A1EB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notice ADD CONSTRAINT FK_480D45C280F7A1EB FOREIGN KEY (related_for_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
    }
}
