<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231206163008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE communication_nationality_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_office_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_package_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_provinces_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_recharge_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_sale_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_nationality (id INT NOT NULL, environment_id INT NOT NULL, name VARCHAR(255) NOT NULL, code_alpha2 VARCHAR(2) DEFAULT NULL, code_alpha3 VARCHAR(3) NOT NULL, com_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A1A56834903E3A94 ON communication_nationality (environment_id)');
        $this->addSql('CREATE TABLE communication_office (id INT NOT NULL, province_id INT NOT NULL, environment_id INT NOT NULL, name VARCHAR(255) NOT NULL, com_id VARCHAR(255) NOT NULL, is_airport BOOLEAN DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CFDBADC0E946114A ON communication_office (province_id)');
        $this->addSql('CREATE INDEX IDX_CFDBADC0903E3A94 ON communication_office (environment_id)');
        $this->addSql('CREATE TABLE communication_package (id INT NOT NULL, environment_id INT NOT NULL, com_id INT NOT NULL, communication_description VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, com_price DOUBLE PRECISION NOT NULL, com_package_type VARCHAR(1) NOT NULL, com_currency VARCHAR(3) NOT NULL, com_info JSON NOT NULL, is_offer BOOLEAN NOT NULL, is_enabled BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ABB94ECF903E3A94 ON communication_package (environment_id)');
        $this->addSql('COMMENT ON COLUMN communication_package.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.end_date_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_provinces (id INT NOT NULL, environment_id INT NOT NULL, name VARCHAR(255) NOT NULL, com_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8E709434903E3A94 ON communication_provinces (environment_id)');
        $this->addSql('CREATE TABLE communication_recharge (id INT NOT NULL, package_id INT NOT NULL, tenant_id INT NOT NULL, phone_number VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, price DOUBLE PRECISION NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, rate DOUBLE PRECISION NOT NULL, sequence INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3DBB46929033212A ON communication_recharge (tenant_id)');
        $this->addSql('CREATE INDEX IDX_3DBB4692F44CABFF ON communication_recharge (package_id)');
        $this->addSql('COMMENT ON COLUMN communication_recharge.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_recharge.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_sale (id INT NOT NULL, package_id INT NOT NULL, tenant_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(2) NOT NULL, sequence INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, status INT NULL, client_info JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DA9CB8D19033212A ON communication_sale (tenant_id)');
        $this->addSql('CREATE INDEX IDX_DA9CB8D1F44CABFF ON communication_sale (package_id)');
        $this->addSql('COMMENT ON COLUMN communication_sale.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_sale.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE communication_nationality ADD CONSTRAINT FK_A1A56834903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_office ADD CONSTRAINT FK_CFDBADC0E946114A FOREIGN KEY (province_id) REFERENCES communication_provinces (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_office ADD CONSTRAINT FK_CFDBADC0903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT FK_ABB94ECF903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_provinces ADD CONSTRAINT FK_8E709434903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_recharge ADD CONSTRAINT FK_3DBB46929033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_recharge ADD CONSTRAINT FK_3DBB4692F44CABFF FOREIGN KEY (package_id) REFERENCES communication_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT FK_DA9CB8D19033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT FK_DA9CB8D1F44CABFF FOREIGN KEY (package_id) REFERENCES communication_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE communication_nationality_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_office_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_package_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_provinces_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_recharge_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_sale_id_seq CASCADE');
        $this->addSql('ALTER TABLE communication_nationality DROP CONSTRAINT FK_A1A56834903E3A94');
        $this->addSql('ALTER TABLE communication_office DROP CONSTRAINT FK_CFDBADC0E946114A');
        $this->addSql('ALTER TABLE communication_office DROP CONSTRAINT FK_CFDBADC0903E3A94');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT FK_ABB94ECF903E3A94');
        $this->addSql('ALTER TABLE communication_provinces DROP CONSTRAINT FK_8E709434903E3A94');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT FK_3DBB46929033212A');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT FK_3DBB4692F44CABFF');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D19033212A');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D1F44CABFF');
        $this->addSql('DROP TABLE communication_nationality');
        $this->addSql('DROP TABLE communication_office');
        $this->addSql('DROP TABLE communication_package');
        $this->addSql('DROP TABLE communication_provinces');
        $this->addSql('DROP TABLE communication_recharge');
        $this->addSql('DROP TABLE communication_sale');
    }
}
