<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240217225035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE tenant_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE communication_product_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_product (id INT NOT NULL, package_id INT NOT NULL, package_type VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, enabled BOOLEAN NOT NULL, initial_date TIMESTAMP(0) WITH TIME ZONE NOT NULL, end_date_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, product_type VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN communication_product.initial_date IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_product.end_date_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN communication_product.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP TABLE tenant');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE communication_product_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE tenant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE tenant (id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('DROP TABLE communication_product');
    }
}
