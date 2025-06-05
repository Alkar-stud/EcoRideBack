<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250603094926 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE validation ADD support_by_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation ADD CONSTRAINT FK_16AC5B6E585D36DC FOREIGN KEY (support_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_16AC5B6E585D36DC ON validation (support_by_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE validation DROP FOREIGN KEY FK_16AC5B6E585D36DC
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_16AC5B6E585D36DC ON validation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE validation DROP support_by_id
        SQL);
    }
}
