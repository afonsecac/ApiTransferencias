<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216221136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_package ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT FK_ABB94ECF7E3C61F9 FOREIGN KEY (owner_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ABB94ECF7E3C61F9 ON communication_package (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT FK_ABB94ECF7E3C61F9');
        $this->addSql('DROP INDEX IDX_ABB94ECF7E3C61F9');
        $this->addSql('ALTER TABLE communication_package DROP owner_id');
    }
}
