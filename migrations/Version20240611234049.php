<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240611234049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT fk_c327c7a87e3c61f9');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT FK_C327C7A89033212A');
        $this->addSql('DROP INDEX idx_c327c7a87e3c61f9');
        $this->addSql('ALTER TABLE communication_client_package ADD know_more VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_client_package DROP owner_id');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT FK_C327C7A89033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT fk_c327c7a89033212a');
        $this->addSql('ALTER TABLE communication_client_package ADD owner_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_client_package DROP know_more');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT fk_c327c7a87e3c61f9 FOREIGN KEY (owner_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT fk_c327c7a89033212a FOREIGN KEY (tenant_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c327c7a87e3c61f9 ON communication_client_package (owner_id)');
    }
}
