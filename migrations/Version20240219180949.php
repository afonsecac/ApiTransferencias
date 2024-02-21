<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240219180949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_sale_info ALTER commercial_office_id DROP NOT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ALTER nationality_id DROP NOT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ALTER type TYPE VARCHAR(10)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_sale_info ALTER commercial_office_id SET NOT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ALTER nationality_id SET NOT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ALTER type TYPE VARCHAR(255)');
    }
}
