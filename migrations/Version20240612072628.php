<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612072628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_promotions ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT FK_3EA2E68E9033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3EA2E68E9033212A ON communication_promotions (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT FK_3EA2E68E9033212A');
        $this->addSql('DROP INDEX IDX_3EA2E68E9033212A');
        $this->addSql('ALTER TABLE communication_promotions DROP tenant_id');
    }
}
