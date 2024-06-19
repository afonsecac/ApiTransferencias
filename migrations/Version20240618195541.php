<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240618195541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_client_package ADD amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_client_package ADD currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_promotions ALTER info_description DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions ALTER info_description SET NOT NULL');
        $this->addSql('ALTER TABLE communication_client_package DROP amount');
        $this->addSql('ALTER TABLE communication_client_package DROP currency');
    }
}
