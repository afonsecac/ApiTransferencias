<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231125225927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id UUID NOT NULL, company_name VARCHAR(255) NOT NULL, company_address TEXT DEFAULT NULL, company_country VARCHAR(3) NOT NULL, company_zip_code VARCHAR(12) DEFAULT NULL, company_email VARCHAR(120) NOT NULL, company_phone_number VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, remove_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, discount_of_client DOUBLE PRECISION NOT NULL, company_identification VARCHAR(255) NOT NULL, company_identification_type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX index_company_identification ON client (company_identification, company_identification_type)');
        $this->addSql('CREATE UNIQUE INDEX unique_company_information ON client (company_country, company_identification, company_identification_type)');
        $this->addSql('COMMENT ON COLUMN client.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.remove_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE environment (id UUID NOT NULL, type VARCHAR(10) NOT NULL, base_path VARCHAR(255) NOT NULL, scope VARCHAR(255) DEFAULT NULL, tenant_id VARCHAR(255) DEFAULT NULL, provider_name VARCHAR(255) NOT NULL, client_secret VARCHAR(255) NOT NULL, client_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, discount DOUBLE PRECISION NOT NULL, discount_type VARCHAR(3) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX index_type_environment ON environment (type)');
        $this->addSql('CREATE INDEX index_provider_name ON environment (provider_name)');
        $this->addSql('CREATE UNIQUE INDEX unique_Provider_Info ON environment (type, provider_name)');
        $this->addSql('COMMENT ON COLUMN environment.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN environment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE permission (id UUID NOT NULL, environment_id UUID NOT NULL, client_id UUID NOT NULL, uuid VARCHAR(180) NOT NULL, roles JSON NOT NULL, access_token UUID NOT NULL, discount DOUBLE PRECISION NOT NULL, discount_unit VARCHAR(3) NOT NULL, commission DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E04992AAD17F50A6 ON permission (uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E04992AAB6A2DD68 ON permission (access_token)');
        $this->addSql('CREATE INDEX IDX_E04992AA903E3A94 ON permission (environment_id)');
        $this->addSql('CREATE INDEX IDX_E04992AA19EB6921 ON permission (client_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_environment_by_client ON permission (environment_id, client_id)');
        $this->addSql('COMMENT ON COLUMN permission.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN permission.environment_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN permission.client_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN permission.access_token IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, company_id UUID DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, permission TEXT NOT NULL, first_name VARCHAR(60) NOT NULL, middle_name VARCHAR(60) DEFAULT NULL, last_name VARCHAR(120) NOT NULL, job_title VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT NULL, is_check_validation BOOLEAN NOT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_check_validation_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, phone_number VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649979B1AD6 ON "user" (company_id)');
        $this->addSql('COMMENT ON COLUMN "user".id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN "user".company_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN "user".permission IS \'(DC2Type:array)\'');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".is_check_validation_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE user_password (id UUID NOT NULL, user_historic_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, historic_password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D54FA2D5FBE881F1 ON user_password (user_historic_id)');
        $this->addSql('COMMENT ON COLUMN user_password.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_password.user_historic_id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN user_password.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_password ADD CONSTRAINT FK_D54FA2D5FBE881F1 FOREIGN KEY (user_historic_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE permission DROP CONSTRAINT FK_E04992AA903E3A94');
        $this->addSql('ALTER TABLE permission DROP CONSTRAINT FK_E04992AA19EB6921');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649979B1AD6');
        $this->addSql('ALTER TABLE user_password DROP CONSTRAINT FK_D54FA2D5FBE881F1');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE user_password');
    }
}
