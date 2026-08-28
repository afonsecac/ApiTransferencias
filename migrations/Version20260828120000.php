<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Extiende client_provider_routing con clasificación por servicio/subservicio
 * (mismo vocabulario que communication_package/communication_contract, ver
 * ServiceCategoryKey) — permite reglas de enrutado específicas por
 * categoría, no solo por cliente/entorno/tipo de venta.
 *
 * Sin backfill: las filas existentes quedan con service_key = '|' (comodín
 * "cualquier categoría"), que es exactamente su semántica actual —
 * ProviderDispatchResolver las sigue considerando aplicables a cualquier
 * paquete.
 */
final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade service_name/subservice_name/service_key a client_provider_routing y amplía uniq_cpr_scope';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_provider_routing ADD COLUMN IF NOT EXISTS service_name VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE client_provider_routing ADD COLUMN IF NOT EXISTS subservice_name VARCHAR(255) NULL');
        $this->addSql("ALTER TABLE client_provider_routing ADD COLUMN IF NOT EXISTS service_key VARCHAR(191) NOT NULL DEFAULT '|'");

        // Reemplaza uniq_cpr_scope (Version20260801120000) incorporando
        // service_key: dos filas activas del mismo (client, environment,
        // sale_type) ahora pueden coexistir si son de categorías distintas.
        $this->addSql('DROP INDEX IF EXISTS uniq_cpr_scope');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_cpr_scope
                ON client_provider_routing (client_id, environment_id, sale_type, service_key)
                NULLS NOT DISTINCT
                WHERE is_active = TRUE
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cpr_service_key ON client_provider_routing (service_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_cpr_scope');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_cpr_scope
                ON client_provider_routing (client_id, environment_id, sale_type)
                NULLS NOT DISTINCT
                WHERE is_active = TRUE
        SQL);
        $this->addSql('DROP INDEX IF EXISTS idx_cpr_service_key');
        $this->addSql('ALTER TABLE client_provider_routing DROP COLUMN IF EXISTS service_key');
        $this->addSql('ALTER TABLE client_provider_routing DROP COLUMN IF EXISTS subservice_name');
        $this->addSql('ALTER TABLE client_provider_routing DROP COLUMN IF EXISTS service_name');
    }
}
