<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240920151903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE identification_type_id_seq CASCADE');
        $this->addSql('DROP TABLE identification_type');
        $this->addSql('ALTER TABLE communication_price_package ADD price_package_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6A40C4A4FB FOREIGN KEY (price_package_id) REFERENCES communication_price_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1858BD6A40C4A4FB ON communication_price_package (price_package_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE identification_type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE identification_type (id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6A40C4A4FB');
        $this->addSql('DROP INDEX IDX_1858BD6A40C4A4FB');
        $this->addSql('ALTER TABLE communication_price_package DROP price_package_id');
    }
}
