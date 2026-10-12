<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea processed_event: registro de eventos de dominio ya procesados por cada consumidor (idempotencia del outbox)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS processed_event (consumer VARCHAR(190) NOT NULL, event_id VARCHAR(64) NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (consumer, event_id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_processed_event_processed_at ON processed_event (processed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS processed_event');
    }
}
