<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240614071557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_client_package ADD price_client_package_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT FK_C327C7A8728722E6 FOREIGN KEY (price_client_package_id) REFERENCES communication_price_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C327C7A8728722E6 ON communication_client_package (price_client_package_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT FK_C327C7A8728722E6');
        $this->addSql('DROP INDEX IDX_C327C7A8728722E6');
        $this->addSql('ALTER TABLE communication_client_package DROP price_client_package_id');
    }
}
