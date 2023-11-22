<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231122031030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE client_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE environment_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE permission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE client (id INT NOT NULL, uuid VARCHAR(180) NOT NULL, roles JSON NOT NULL, company_name VARCHAR(255) NOT NULL, company_email VARCHAR(120) NOT NULL, company_phone VARCHAR(20) NOT NULL, company_tel VARCHAR(20) NOT NULL, company_site VARCHAR(100) NOT NULL, company_description TEXT NOT NULL, company_legal_representative VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, inactive_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_accepted_politics BOOLEAN NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_accepted_offer BOOLEAN NOT NULL, accepted_offer_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455D17F50A6 ON client (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7440455A063DE11 ON client (company_email)');
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.inactive_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.accepted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.accepted_offer_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE environment (id INT NOT NULL, environment VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, url_path VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX indexTxEnvironment ON environment (environment)');
        $this->addSql('CREATE INDEX indexTxEnvironmentUrlPath ON environment (url_path)');
        $this->addSql('CREATE UNIQUE INDEX uniqueTxEnvironment ON environment (environment, url_path)');
        $this->addSql('COMMENT ON COLUMN environment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE permission (id INT NOT NULL, client_id INT NOT NULL, environment_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, token_id UUID NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E04992AA41DEE7B9 ON permission (token_id)');
        $this->addSql('CREATE INDEX IDX_E04992AA19EB6921 ON permission (client_id)');
        $this->addSql('CREATE INDEX IDX_E04992AA903E3A94 ON permission (environment_id)');
        $this->addSql('COMMENT ON COLUMN permission.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN permission.removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN permission.token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE client_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE environment_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE permission_id_seq CASCADE');
        $this->addSql('ALTER TABLE permission DROP CONSTRAINT FK_E04992AA19EB6921');
        $this->addSql('ALTER TABLE permission DROP CONSTRAINT FK_E04992AA903E3A94');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE permission');
    }
}
