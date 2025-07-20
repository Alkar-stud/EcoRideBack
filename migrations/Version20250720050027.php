<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated for existing user's preferences
 */
final class Version20250720050027 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO `preferences` (`user_id`, `libelle`, `description`, `created_at`) VALUES
                (2, 'smokingAllowed', 'no', NOW()),
                (2, 'petsAllowed', 'no', NOW()),
                (3, 'smokingAllowed', 'no', NOW()),
                (3, 'petsAllowed', 'no', NOW()),
                (4, 'smokingAllowed', 'no', NOW()),
                (4, 'petsAllowed', 'no', NOW()),
                (2, 'Musique', 'J\'écoute du métal', NOW()),
                (5, 'smokingAllowed', 'no', NOW()),
                (5, 'petsAllowed', 'no', NOW()),
                (6, 'smokingAllowed', 'no', NOW()),
                (6, 'petsAllowed', 'no', NOW());
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            TRUNCATE `preferences`;
        SQL);
    }
}
