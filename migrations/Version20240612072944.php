<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612072944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT fk_3ea2e68e26122b23');
        $this->addSql('DROP INDEX idx_3ea2e68e26122b23');
        $this->addSql('ALTER TABLE communication_promotions RENAME COLUMN provider_id_id TO product_id');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT FK_3EA2E68E4584665A FOREIGN KEY (product_id) REFERENCES communication_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3EA2E68E4584665A ON communication_promotions (product_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT FK_3EA2E68E4584665A');
        $this->addSql('DROP INDEX IDX_3EA2E68E4584665A');
        $this->addSql('ALTER TABLE communication_promotions RENAME COLUMN product_id TO provider_id_id');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT fk_3ea2e68e26122b23 FOREIGN KEY (provider_id_id) REFERENCES communication_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3ea2e68e26122b23 ON communication_promotions (provider_id_id)');
    }
}
