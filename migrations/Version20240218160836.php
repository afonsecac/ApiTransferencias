<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240218160836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE communication_price_table_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE communication_price_table (id INT NOT NULL, client_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, start_price DOUBLE PRECISION DEFAULT NULL, end_price DOUBLE PRECISION DEFAULT NULL, range_price_currency VARCHAR(3) NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F680646219EB6921 ON communication_price_table (client_id)');
        $this->addSql('COMMENT ON COLUMN communication_price_table.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE communication_price_table ADD CONSTRAINT FK_F680646219EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE communication_price_table_id_seq CASCADE');
        $this->addSql('ALTER TABLE communication_price_table DROP CONSTRAINT FK_F680646219EB6921');
        $this->addSql('DROP TABLE communication_price_table');
    }
}
