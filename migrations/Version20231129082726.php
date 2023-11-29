<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231129082726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE account_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE bank_card_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE beneficiary_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE city_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE client_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE country_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE env_auth_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE environment_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE province_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sender_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sys_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE transfer_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_password_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE account (id INT NOT NULL, environment_id INT NOT NULL, client_id INT NOT NULL, roles JSON NOT NULL, access_token UUID NOT NULL, discount DOUBLE PRECISION NOT NULL, discount_unit VARCHAR(3) NOT NULL, commission DOUBLE PRECISION NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, origin VARCHAR(255) NOT NULL, account_id INT DEFAULT NULL, environment_name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656A4B6A2DD68 ON account (access_token)');
        $this->addSql('CREATE INDEX IDX_7D3656A4903E3A94 ON account (environment_id)');
        $this->addSql('CREATE INDEX IDX_7D3656A419EB6921 ON account (client_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_environment_by_client ON account (environment_id, client_id)');
        $this->addSql('COMMENT ON COLUMN account.access_token IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN account.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN account.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN account.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE bank_card (id INT NOT NULL, beneficiary_id INT NOT NULL, rebus_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, card_number VARCHAR(20) NOT NULL, beneficiary_card_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BC74CA5DECCAAFA0 ON bank_card (beneficiary_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_card_by_beneficiary_tenant ON bank_card (card_number, beneficiary_card_id)');
        $this->addSql('COMMENT ON COLUMN bank_card.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bank_card.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE beneficiary (id INT NOT NULL, city_id INT NOT NULL, tenant_id INT NOT NULL, environment_id INT NOT NULL, first_name VARCHAR(60) NOT NULL, middle_name VARCHAR(60) DEFAULT NULL, last_name VARCHAR(120) NOT NULL, email VARCHAR(120) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, home_phone VARCHAR(20) DEFAULT NULL, date_of_birth DATE NOT NULL, gender VARCHAR(1) DEFAULT NULL, gender_at_birth VARCHAR(1) DEFAULT NULL, address_line1 TEXT NOT NULL, address_line2 TEXT DEFAULT NULL, city_of_residence_id INT NOT NULL, zip_code VARCHAR(15) NOT NULL, identification_number VARCHAR(30) NOT NULL, is_active BOOLEAN DEFAULT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, remove_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7ABF446A8BAC62AF ON beneficiary (city_id)');
        $this->addSql('CREATE INDEX IDX_7ABF446A9033212A ON beneficiary (tenant_id)');
        $this->addSql('CREATE INDEX IDX_7ABF446A903E3A94 ON beneficiary (environment_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_beneficiary_by_environment ON beneficiary (identification_number, environment_id)');
        $this->addSql('COMMENT ON COLUMN beneficiary.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN beneficiary.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN beneficiary.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN beneficiary.remove_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE city (id INT NOT NULL, environment_id INT NOT NULL, province_id INT NOT NULL, name VARCHAR(255) NOT NULL, rebus_abbrev VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL, rebus_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2D5B0234903E3A94 ON city (environment_id)');
        $this->addSql('CREATE INDEX IDX_2D5B0234E946114A ON city (province_id)');
        $this->addSql('CREATE TABLE client (id INT NOT NULL, company_name VARCHAR(255) NOT NULL, company_address TEXT DEFAULT NULL, company_country VARCHAR(3) NOT NULL, company_zip_code VARCHAR(12) DEFAULT NULL, company_email VARCHAR(120) NOT NULL, company_phone_number VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, remove_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN DEFAULT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, discount_of_client DOUBLE PRECISION NOT NULL, company_identification VARCHAR(255) NOT NULL, company_identification_type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX index_company_identification ON client (company_identification, company_identification_type)');
        $this->addSql('CREATE UNIQUE INDEX unique_company_information ON client (company_country, company_identification, company_identification_type)');
        $this->addSql('COMMENT ON COLUMN client.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.remove_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN client.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE country (id INT NOT NULL, environment_id INT NOT NULL, name VARCHAR(255) NOT NULL, rebus_id INT NOT NULL, rebus_status_id INT DEFAULT NULL, rebus_status_name VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, alpha2_code VARCHAR(2) DEFAULT NULL, alpha3_code VARCHAR(3) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5373C966C65DF878 ON country (rebus_id)');
        $this->addSql('CREATE INDEX IDX_5373C966903E3A94 ON country (environment_id)');
        $this->addSql('CREATE INDEX index_alpha2_country ON country (alpha2_code)');
        $this->addSql('CREATE INDEX index_alpha3_country ON country (alpha3_code)');
        $this->addSql('CREATE UNIQUE INDEX unique_country_codes ON country (alpha2_code, alpha3_code)');
        $this->addSql('CREATE TABLE env_auth (id INT NOT NULL, permission_id INT NOT NULL, token_auth TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98029F8D9ADA853B ON env_auth (token_auth)');
        $this->addSql('CREATE INDEX IDX_98029F8DFED90CCA ON env_auth (permission_id)');
        $this->addSql('COMMENT ON COLUMN env_auth.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN env_auth.closed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE environment (id INT NOT NULL, type VARCHAR(10) NOT NULL, base_path VARCHAR(255) NOT NULL, scope VARCHAR(255) DEFAULT NULL, tenant_id VARCHAR(255) DEFAULT NULL, provider_name VARCHAR(255) NOT NULL, client_secret VARCHAR(255) NOT NULL, client_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, discount DOUBLE PRECISION NOT NULL, discount_type VARCHAR(3) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX index_type_environment ON environment (type)');
        $this->addSql('CREATE INDEX index_provider_name ON environment (provider_name)');
        $this->addSql('CREATE UNIQUE INDEX unique_Provider_Info ON environment (type, provider_name)');
        $this->addSql('COMMENT ON COLUMN environment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN environment.is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE province (id INT NOT NULL, country_id INT NOT NULL, environment_id INT NOT NULL, name VARCHAR(255) NOT NULL, rebus_province_id INT NOT NULL, rebus_abbrev VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4ADAD40BF92F3E70 ON province (country_id)');
        $this->addSql('CREATE INDEX IDX_4ADAD40B903E3A94 ON province (environment_id)');
        $this->addSql('CREATE TABLE sender (id INT NOT NULL, tenant_id INT NOT NULL, first_name VARCHAR(60) NOT NULL, middle_name VARCHAR(60) DEFAULT NULL, last_name VARCHAR(120) NOT NULL, email VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, address TEXT DEFAULT NULL, country_alpha3_code VARCHAR(3) NOT NULL, identification_type VARCHAR(50) NOT NULL, identification VARCHAR(255) NOT NULL, rebus_sender_id INT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5F004ACF9033212A ON sender (tenant_id)');
        $this->addSql('CREATE INDEX index_identification_sender ON sender (identification)');
        $this->addSql('CREATE UNIQUE INDEX unique_identification_sender ON sender (identification_type, identification)');
        $this->addSql('CREATE UNIQUE INDEX unique__rebus_identification_sender ON sender (rebus_sender_id, identification)');
        $this->addSql('COMMENT ON COLUMN sender.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sender.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE sys_config (id INT NOT NULL, property_name VARCHAR(255) NOT NULL, property_value TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, clients JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_30FEA291413BC13C ON sys_config (property_name)');
        $this->addSql('COMMENT ON COLUMN sys_config.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sys_config.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN sys_config.removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE transfer (id INT NOT NULL, sender_id INT DEFAULT NULL, beneficiary_id INT DEFAULT NULL, tenant_id INT NOT NULL, amount_deposit DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, amount_commission DOUBLE PRECISION NOT NULL, currency_commission VARCHAR(3) NOT NULL, total_amount DOUBLE PRECISION NOT NULL, currency_total VARCHAR(3) NOT NULL, rate_to_change DOUBLE PRECISION NOT NULL, rebus_pay_id INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status_id INT NOT NULL, status_name VARCHAR(20) NOT NULL, reason_note TEXT DEFAULT NULL, sender_name VARCHAR(255) DEFAULT NULL, beneficiary_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4034A3C09033212A ON transfer (tenant_id)');
        $this->addSql('CREATE INDEX IDX_4034A3C0F624B39D ON transfer (sender_id)');
        $this->addSql('CREATE INDEX IDX_4034A3C0ECCAAFA0 ON transfer (beneficiary_id)');
        $this->addSql('COMMENT ON COLUMN transfer.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transfer.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, company_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, permission JSON NOT NULL, first_name VARCHAR(60) NOT NULL, middle_name VARCHAR(60) DEFAULT NULL, last_name VARCHAR(120) NOT NULL, job_title VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT NULL, is_check_validation BOOLEAN NOT NULL, is_active_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_check_validation_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, phone_number VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649979B1AD6 ON "user" (company_id)');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".is_active_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".is_check_validation_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".removed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE user_password (id INT NOT NULL, user_historic_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, historic_password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D54FA2D5FBE881F1 ON user_password (user_historic_id)');
        $this->addSql('COMMENT ON COLUMN user_password.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A4903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A419EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bank_card ADD CONSTRAINT FK_BC74CA5DECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A8BAC62AF FOREIGN KEY (city_id) REFERENCES city (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A9033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE city ADD CONSTRAINT FK_2D5B0234E946114A FOREIGN KEY (province_id) REFERENCES province (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE country ADD CONSTRAINT FK_5373C966903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE env_auth ADD CONSTRAINT FK_98029F8DFED90CCA FOREIGN KEY (permission_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE province ADD CONSTRAINT FK_4ADAD40BF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE province ADD CONSTRAINT FK_4ADAD40B903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sender ADD CONSTRAINT FK_5F004ACF9033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C09033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0F624B39D FOREIGN KEY (sender_id) REFERENCES sender (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES bank_card (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649979B1AD6 FOREIGN KEY (company_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_password ADD CONSTRAINT FK_D54FA2D5FBE881F1 FOREIGN KEY (user_historic_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE account_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE bank_card_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE beneficiary_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE city_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE client_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE country_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE env_auth_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE environment_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE province_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sender_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sys_config_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE transfer_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "user_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE user_password_id_seq CASCADE');
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A4903E3A94');
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A419EB6921');
        $this->addSql('ALTER TABLE bank_card DROP CONSTRAINT FK_BC74CA5DECCAAFA0');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A8BAC62AF');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A9033212A');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A903E3A94');
        $this->addSql('ALTER TABLE city DROP CONSTRAINT FK_2D5B0234903E3A94');
        $this->addSql('ALTER TABLE city DROP CONSTRAINT FK_2D5B0234E946114A');
        $this->addSql('ALTER TABLE country DROP CONSTRAINT FK_5373C966903E3A94');
        $this->addSql('ALTER TABLE env_auth DROP CONSTRAINT FK_98029F8DFED90CCA');
        $this->addSql('ALTER TABLE province DROP CONSTRAINT FK_4ADAD40BF92F3E70');
        $this->addSql('ALTER TABLE province DROP CONSTRAINT FK_4ADAD40B903E3A94');
        $this->addSql('ALTER TABLE sender DROP CONSTRAINT FK_5F004ACF9033212A');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C09033212A');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C0F624B39D');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C0ECCAAFA0');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649979B1AD6');
        $this->addSql('ALTER TABLE user_password DROP CONSTRAINT FK_D54FA2D5FBE881F1');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE bank_card');
        $this->addSql('DROP TABLE beneficiary');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE env_auth');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE province');
        $this->addSql('DROP TABLE sender');
        $this->addSql('DROP TABLE sys_config');
        $this->addSql('DROP TABLE transfer');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE user_password');
    }
}
