<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231126215619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sender (id UUID NOT NULL, tenant_id UUID NOT NULL, first_name VARCHAR(60) NOT NULL, middle_name VARCHAR(60) DEFAULT NULL, last_name VARCHAR(120) NOT NULL, email VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, address TEXT DEFAULT NULL, country_alpha3_code VARCHAR(3) NOT NULL, identification_type VARCHAR(50) NOT NULL, identification VARCHAR(255) NOT NULL, rebus_sender_id INT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5F004ACF9033212A ON sender (tenant_id)');
        $this->addSql('CREATE INDEX index_identification_sender ON sender (identification)');
        $this->addSql('CREATE UNIQUE INDEX unique_identification_sender ON sender (identification_type, identification)');
        $this->addSql('CREATE UNIQUE INDEX unique__rebus_identification_sender ON sender (rebus_sender_id, identification)');
        $this->addSql('COMMENT ON COLUMN sender.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN sender.tenant_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN sender.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sender.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE sender ADD CONSTRAINT FK_5F004ACF9033212A FOREIGN KEY (tenant_id) REFERENCES permission (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sender DROP CONSTRAINT FK_5F004ACF9033212A');
        $this->addSql('DROP TABLE sender');
    }
}
