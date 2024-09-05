<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240905094720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_d2cb95503faf6132');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D2CB95503FAF6132 ON balance_operation (communication_sale_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_D2CB95503FAF6132');
        $this->addSql('CREATE INDEX idx_d2cb95503faf6132 ON balance_operation (communication_sale_id)');
    }
}
