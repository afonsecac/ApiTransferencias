<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612083651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT fk_c327c7a87017db82');
        $this->addSql('DROP INDEX idx_c327c7a87017db82');
        $this->addSql('ALTER TABLE communication_client_package DROP package_client_price_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_client_package ADD package_client_price_id INT NOT NULL');
        $this->addSql('ALTER TABLE communication_client_package ADD CONSTRAINT fk_c327c7a87017db82 FOREIGN KEY (package_client_price_id) REFERENCES communication_price_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_c327c7a87017db82 ON communication_client_package (package_client_price_id)');
    }
}
