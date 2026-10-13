<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade el área opcional (ADR-001, mercado) a client_provider_routing.
 *
 * Sin backfill: ninguna fila existente tiene área, así que todas quedan con area_name NULL y su service_key
 * no cambia. El índice único uniq_cpr_scope no se toca: service_key ya incluirá el área en las filas que la
 * tengan, y por eso dos reglas del mismo cliente con áreas distintas pueden coexistir.
 */
final class Version20261012140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade area_name (nullable, sin backfill) a client_provider_routing para enrutar por área (C1, ADR-001)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_provider_routing ADD COLUMN IF NOT EXISTS area_name VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        // Sin filas con área es inocuo; con filas con área perdería esa información y sus service_key quedarían inconsistentes.
        $this->addSql('ALTER TABLE client_provider_routing DROP COLUMN IF EXISTS area_name');
    }
}
