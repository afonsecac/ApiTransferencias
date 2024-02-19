<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240216221445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE tenant_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE tenant (id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE communication_package ADD tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_package ADD CONSTRAINT FK_ABB94ECF9033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_ABB94ECF9033212A ON communication_package (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE tenant_id_seq CASCADE');
        $this->addSql('DROP TABLE tenant');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT FK_ABB94ECF9033212A');
        $this->addSql('DROP INDEX IDX_ABB94ECF9033212A');
        $this->addSql('ALTER TABLE communication_package DROP tenant_id');
    }
}
