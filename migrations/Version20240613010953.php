<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240613010953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT fk_1858bd6a19eb6921');
        $this->addSql('DROP INDEX idx_1858bd6a19eb6921');
        $this->addSql('ALTER TABLE communication_price_package RENAME COLUMN client_id TO tenant_id');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6A9033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1858BD6A9033212A ON communication_price_package (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6A9033212A');
        $this->addSql('DROP INDEX IDX_1858BD6A9033212A');
        $this->addSql('ALTER TABLE communication_price_package RENAME COLUMN tenant_id TO client_id');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT fk_1858bd6a19eb6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_1858bd6a19eb6921 ON communication_price_package (client_id)');
    }
}
