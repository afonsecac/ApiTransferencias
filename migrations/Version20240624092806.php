<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240624092806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_price_package ADD environment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD CONSTRAINT FK_1858BD6A903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1858BD6A903E3A94 ON communication_price_package (environment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT FK_1858BD6A903E3A94');
        $this->addSql('DROP INDEX IDX_1858BD6A903E3A94');
        $this->addSql('ALTER TABLE communication_price_package DROP environment_id');
    }
}
