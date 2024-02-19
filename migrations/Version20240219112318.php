<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240219112318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE communication_sale_info_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_sale_info (id INT NOT NULL, package_id INT NOT NULL, tenant_id INT NOT NULL, commercial_office_id INT NOT NULL, nationality_id INT NOT NULL, transaction_order VARCHAR(15) DEFAULT NULL, transaction_id VARCHAR(15) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, client_transaction_id VARCHAR(255) NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, discount DOUBLE PRECISION DEFAULT NULL, amount_tax DOUBLE PRECISION DEFAULT NULL, total_price DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, phone_number VARCHAR(15) DEFAULT NULL, identification_number VARCHAR(15) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, arrival_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, phone_client_number VARCHAR(15) DEFAULT NULL, office_is_airport BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75211DD1F44CABFF ON communication_sale_info (package_id)');
        $this->addSql('CREATE INDEX IDX_75211DD19033212A ON communication_sale_info (tenant_id)');
        $this->addSql('CREATE INDEX IDX_75211DD1FEC7B730 ON communication_sale_info (commercial_office_id)');
        $this->addSql('CREATE INDEX IDX_75211DD11C9DA55 ON communication_sale_info (nationality_id)');
        $this->addSql('COMMENT ON COLUMN communication_sale_info.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_sale_info.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_sale_info.arrival_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE communication_sale_info ADD CONSTRAINT FK_75211DD1F44CABFF FOREIGN KEY (package_id) REFERENCES communication_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale_info ADD CONSTRAINT FK_75211DD19033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale_info ADD CONSTRAINT FK_75211DD1FEC7B730 FOREIGN KEY (commercial_office_id) REFERENCES communication_office (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale_info ADD CONSTRAINT FK_75211DD11C9DA55 FOREIGN KEY (nationality_id) REFERENCES communication_nationality (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE communication_sale_info_id_seq CASCADE');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT FK_75211DD1F44CABFF');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT FK_75211DD19033212A');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT FK_75211DD1FEC7B730');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT FK_75211DD11C9DA55');
        $this->addSql('DROP TABLE communication_sale_info');
    }
}
