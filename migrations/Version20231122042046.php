<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231122042046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE beneficiary_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sender_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE transaction_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE beneficiary (id INT NOT NULL, first_name VARCHAR(50) NOT NULL, middle_name VARCHAR(50) DEFAULT NULL, last_name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, email VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, home_phone VARCHAR(20) DEFAULT NULL, dob TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, gender INT NOT NULL, gender_at_birth INT NOT NULL, address_line1 VARCHAR(255) NOT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(10) NOT NULL, province_id INT NOT NULL, country_id INT NOT NULL, card_number VARCHAR(20) DEFAULT NULL, national_id_number INT DEFAULT NULL, processor_type INT NOT NULL, location VARCHAR(255) NOT NULL, city_id INT NOT NULL, nationality TEXT NOT NULL, user_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN beneficiary.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE sender (id INT NOT NULL, email VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, first_name VARCHAR(50) NOT NULL, last_name VARCHAR(50) NOT NULL, address TEXT NOT NULL, identification VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE transaction (id INT NOT NULL, account_id INT NOT NULL, beneficiary_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, transaction_type INT NOT NULL, sender_id INT NOT NULL, processor_type INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE client ADD origins VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE beneficiary_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sender_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE transaction_id_seq CASCADE');
        $this->addSql('DROP TABLE beneficiary');
        $this->addSql('DROP TABLE sender');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('ALTER TABLE client DROP origins');
    }
}
