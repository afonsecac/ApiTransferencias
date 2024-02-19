<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216224138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT fk_abb94ecf7e3c61f9');
        $this->addSql('DROP INDEX idx_abb94ecf7e3c61f9');
        $this->addSql('ALTER TABLE communication_package ADD package_type VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_package DROP owner_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_package ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_package DROP package_type');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT fk_abb94ecf7e3c61f9 FOREIGN KEY (owner_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_abb94ecf7e3c61f9 ON communication_package (owner_id)');
    }
}
