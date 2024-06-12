<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240611161811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE communication_client_package_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_price_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_price_package_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE communication_promotions_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE product_comm_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE product_comm_benefits_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_client_package (id INT NOT NULL, tenant_id INT NOT NULL, owner_id INT NOT NULL, package_client_price_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, active_start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, active_end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, benefits JSON NOT NULL, description VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, tags JSON NOT NULL, service JSON NOT NULL, destination JSON NOT NULL, validity JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C327C7A89033212A ON communication_client_package (tenant_id)');
        $this->addSql('CREATE INDEX IDX_C327C7A87E3C61F9 ON communication_client_package (owner_id)');
        $this->addSql('CREATE INDEX IDX_C327C7A87017DB82 ON communication_client_package (package_client_price_id)');
        $this->addSql('COMMENT ON COLUMN communication_client_package.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_client_package.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_client_package.active_start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_client_package.active_end_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_price (id INT NOT NULL, start_price DOUBLE PRECISION NOT NULL, end_price DOUBLE PRECISION DEFAULT NULL, currency_price VARCHAR(3) NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, valid_start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, valid_end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN communication_price.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price.valid_start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price.valid_end_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_price_package (id INT NOT NULL, product_id INT NOT NULL, price_used_id INT NOT NULL, client_id INT DEFAULT NULL, price DOUBLE PRECISION NOT NULL, price_currency VARCHAR(3) NOT NULL, amount DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, active_start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, active_end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, currency VARCHAR(3) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1858BD6A4584665A ON communication_price_package (product_id)');
        $this->addSql('CREATE INDEX IDX_1858BD6AB93A138A ON communication_price_package (price_used_id)');
        $this->addSql('CREATE INDEX IDX_1858BD6A19EB6921 ON communication_price_package (client_id)');
        $this->addSql('COMMENT ON COLUMN communication_price_package.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price_package.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price_package.active_start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_price_package.active_end_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_promotions (id INT NOT NULL, provider_id_id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, info_description TEXT NOT NULL, terms JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3EA2E68E26122B23 ON communication_promotions (provider_id_id)');
        $this->addSql('COMMENT ON COLUMN communication_promotions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_promotions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_promotions.start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_promotions.end_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE communication_promotions_communication_package (communication_promotions_id INT NOT NULL, communication_package_id INT NOT NULL, PRIMARY KEY(communication_promotions_id, communication_package_id))');
        $this->addSql('CREATE INDEX IDX_C437BB42BD16652A ON communication_promotions_communication_package (communication_promotions_id)');
        $this->addSql('CREATE INDEX IDX_C437BB4220F57D45 ON communication_promotions_communication_package (communication_package_id)');
        $this->addSql('CREATE TABLE product_comm (id INT NOT NULL, is_approved BOOLEAN NOT NULL, is_processed BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, info JSON DEFAULT NULL, provider_id INT NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN product_comm.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm.start_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm.end_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE product_comm_benefits (id INT NOT NULL, product_comm_id_id INT NOT NULL, benefit_type VARCHAR(20) NOT NULL, benefit_unit_type VARCHAR(20) NOT NULL, benefit_unit VARCHAR(50) NOT NULL, benefit_description VARCHAR(255) DEFAULT NULL, base_info DOUBLE PRECISION NOT NULL, promotion_bonus DOUBLE PRECISION NOT NULL, total_with_tax DOUBLE PRECISION NOT NULL, total_without_tax DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, valid_until_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DDAC53562CD68D7C ON product_comm_benefits (product_comm_id_id)');
        $this->addSql('COMMENT ON COLUMN product_comm_benefits.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm_benefits.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN product_comm_benefits.valid_until_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT FK_C327C7A89033212A FOREIGN KEY (tenant_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT FK_C327C7A87E3C61F9 FOREIGN KEY (owner_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT FK_C327C7A87017DB82 FOREIGN KEY (package_client_price_id) REFERENCES communication_price_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6A4584665A FOREIGN KEY (product_id) REFERENCES communication_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6AB93A138A FOREIGN KEY (price_used_id) REFERENCES communication_price (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6A19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT FK_3EA2E68E26122B23 FOREIGN KEY (provider_id_id) REFERENCES communication_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_package ADD CONSTRAINT FK_C437BB42BD16652A FOREIGN KEY (communication_promotions_id) REFERENCES communication_promotions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_package ADD CONSTRAINT FK_C437BB4220F57D45 FOREIGN KEY (communication_package_id) REFERENCES communication_package (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_comm_benefits ADD CONSTRAINT FK_DDAC53562CD68D7C FOREIGN KEY (product_comm_id_id) REFERENCES product_comm (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE communication_client_package_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_price_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_price_package_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_promotions_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE product_comm_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE product_comm_benefits_id_seq CASCADE');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT FK_C327C7A89033212A');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT FK_C327C7A87E3C61F9');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT FK_C327C7A87017DB82');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6A4584665A');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6AB93A138A');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6A19EB6921');
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT FK_3EA2E68E26122B23');
        $this->addSql('ALTER TABLE communication_promotions_communication_package DROP CONSTRAINT FK_C437BB42BD16652A');
        $this->addSql('ALTER TABLE communication_promotions_communication_package DROP CONSTRAINT FK_C437BB4220F57D45');
        $this->addSql('ALTER TABLE product_comm_benefits DROP CONSTRAINT FK_DDAC53562CD68D7C');
        $this->addSql('DROP TABLE communication_client_package');
        $this->addSql('DROP TABLE communication_price');
        $this->addSql('DROP TABLE communication_price_package');
        $this->addSql('DROP TABLE communication_promotions');
        $this->addSql('DROP TABLE communication_promotions_communication_package');
        $this->addSql('DROP TABLE product_comm');
        $this->addSql('DROP TABLE product_comm_benefits');
    }
}
