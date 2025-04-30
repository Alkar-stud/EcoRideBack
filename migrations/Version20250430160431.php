<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430160431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE trip DROP FOREIGN KEY FK_7656F53B7E3C61F9
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7656F53B7E3C61F9 ON trip
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip CHANGE owner_id driver_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip ADD CONSTRAINT FK_7656F53BC3423909 FOREIGN KEY (driver_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7656F53BC3423909 ON trip (driver_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BC3423909
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7656F53BC3423909 ON trip
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip CHANGE driver_id owner_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE trip ADD CONSTRAINT FK_7656F53B7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7656F53B7E3C61F9 ON trip (owner_id)
        SQL);
    }
}
