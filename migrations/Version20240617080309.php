<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240617080309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE communication_price_table_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_package_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_recharge_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_sale_id_seq CASCADE');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT fk_3dbb46929033212a');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT fk_3dbb4692f44cabff');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT fk_da9cb8d19033212a');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT fk_da9cb8d1139df194');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT fk_da9cb8d1f44cabff');
        $this->addSql('ALTER TABLE communication_price_table DROP CONSTRAINT fk_f680646219eb6921');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT fk_abb94ecf903e3a94');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT fk_abb94ecf9033212a');
        $this->addSql('DROP TABLE communication_recharge');
        $this->addSql('DROP TABLE communication_sale');
        $this->addSql('DROP TABLE communication_price_table');
        $this->addSql('DROP TABLE communication_package');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE communication_price_table_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_package_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_recharge_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_sale_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_recharge (id INT NOT NULL, package_id INT NOT NULL, tenant_id INT NOT NULL, phone_number VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, price DOUBLE PRECISION NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, rate DOUBLE PRECISION NOT NULL, sequence INT NOT NULL, com_info JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_3dbb4692f44cabff ON communication_recharge (package_id)');
        $this->addSql('CREATE INDEX idx_3dbb46929033212a ON communication_recharge (tenant_id)');
        $this->addSql('COMMENT ON COLUMN communication_recharge.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_recharge.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_sale (id INT NOT NULL, package_id INT NOT NULL, tenant_id INT NOT NULL, promotion_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(2) NOT NULL, sequence_info INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, client_info JSON NOT NULL, status VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_da9cb8d1139df194 ON communication_sale (promotion_id)');
        $this->addSql('CREATE INDEX idx_da9cb8d1f44cabff ON communication_sale (package_id)');
        $this->addSql('CREATE INDEX idx_da9cb8d19033212a ON communication_sale (tenant_id)');
        $this->addSql('COMMENT ON COLUMN communication_sale.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_sale.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_price_table (id INT NOT NULL, client_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, start_price DOUBLE PRECISION DEFAULT NULL, end_price DOUBLE PRECISION DEFAULT NULL, range_price_currency VARCHAR(3) NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_f680646219eb6921 ON communication_price_table (client_id)');
        $this->addSql('COMMENT ON COLUMN communication_price_table.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_package (id INT NOT NULL, environment_id INT NOT NULL, tenant_id INT DEFAULT NULL, com_id INT NOT NULL, communication_description VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, com_price DOUBLE PRECISION NOT NULL, com_package_type VARCHAR(1) NOT NULL, com_currency VARCHAR(3) NOT NULL, com_info JSON NOT NULL, is_offer BOOLEAN NOT NULL, is_enabled BOOLEAN DEFAULT NULL, package_type VARCHAR(10) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_abb94ecf9033212a ON communication_package (tenant_id)');
        $this->addSql('CREATE INDEX idx_abb94ecf903e3a94 ON communication_package (environment_id)');
        $this->addSql('COMMENT ON COLUMN communication_package.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_package.end_date_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE communication_recharge ADD CONSTRAINT fk_3dbb46929033212a FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_recharge ADD CONSTRAINT fk_3dbb4692f44cabff FOREIGN KEY (package_id) REFERENCES communication_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT fk_da9cb8d19033212a FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT fk_da9cb8d1139df194 FOREIGN KEY (promotion_id) REFERENCES communication_promotions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT fk_da9cb8d1f44cabff FOREIGN KEY (package_id) REFERENCES communication_client_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_price_table ADD CONSTRAINT fk_f680646219eb6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT fk_abb94ecf903e3a94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT fk_abb94ecf9033212a FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
