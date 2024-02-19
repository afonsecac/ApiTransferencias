<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240217232512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_product ADD environment_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_product ADD CONSTRAINT FK_A69B2DF7903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A69B2DF7903E3A94 ON communication_product (environment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_product DROP CONSTRAINT FK_A69B2DF7903E3A94');
        $this->addSql('DROP INDEX IDX_A69B2DF7903E3A94');
        $this->addSql('ALTER TABLE communication_product DROP environment_id');
    }
}
