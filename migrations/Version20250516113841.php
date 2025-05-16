<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250516113841 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE mails_type (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, subject VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        //Add content
        $this->addSql(<<<'SQL'
            INSERT INTO `mails_type` (`code`, `subject`, `content`, `created_at`) VALUES ('cancel', 'EcoRide - Annulation du covoiturage du {date}', 'Bonjour, <br> le covoiturage du {date} a été annulé.', NOW()), ('passengerValidation', 'EcoRide - Comment s&#039;est passé votre covoiturage ?', 'Comment s&#039;est passé votre covoiturage ? Vous devez valider et si vous souhaitez donner votre avis', NOW()), ('accountUserCreate', 'EcoRide - Bienvenue chez nous', 'Bonjour {pseudo} et bienvenue chez nous !', NOW()), ('forgotPassword', 'EcoRide - Vous avez oublié votre mot de passe ?', 'Bonjour, <br />br&gt;veuillez trouver ci dessous votre mot de passe temporaire.', NOW())
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE mails_type
        SQL);
    }
}
