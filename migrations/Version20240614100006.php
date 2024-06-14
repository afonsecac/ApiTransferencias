<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240614100006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT fk_3ea2e68e9033212a');
        $this->addSql('DROP INDEX idx_3ea2e68e9033212a');
        $this->addSql('ALTER TABLE communication_promotions DROP tenant_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions ADD tenant_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT fk_3ea2e68e9033212a FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3ea2e68e9033212a ON communication_promotions (tenant_id)');
    }
}
