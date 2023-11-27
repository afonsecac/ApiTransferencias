<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231126162148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE city (id UUID NOT NULL, name VARCHAR(255) NOT NULL, rebus_abbrev VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN city.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE TABLE country (id UUID NOT NULL, name VARCHAR(255) NOT NULL, rebus_id INT NOT NULL, rebus_status_id INT DEFAULT NULL, rebus_status_name VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, alpha2_code VARCHAR(2) DEFAULT NULL, alpha3_code VARCHAR(3) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5373C966C65DF878 ON country (rebus_id)');
        $this->addSql('CREATE INDEX index_alpha2_country ON country (alpha2_code)');
        $this->addSql('CREATE INDEX index_alpha3_country ON country (alpha3_code)');
        $this->addSql('CREATE UNIQUE INDEX unique_country_codes ON country (alpha2_code, alpha3_code)');
        $this->addSql('COMMENT ON COLUMN country.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE TABLE province (id UUID NOT NULL, country_id UUID NOT NULL, name VARCHAR(255) NOT NULL, rebus_province_id INT NOT NULL, rebus_abbrev VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4ADAD40BF92F3E70 ON province (country_id)');
        $this->addSql('COMMENT ON COLUMN province.id IS \'(DC2Type:ulid)\'');
        $this->addSql('COMMENT ON COLUMN province.country_id IS \'(DC2Type:ulid)\'');
        $this->addSql('ALTER TABLE province ADD CONSTRAINT FK_4ADAD40BF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE province DROP CONSTRAINT FK_4ADAD40BF92F3E70');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE province');
    }
}
