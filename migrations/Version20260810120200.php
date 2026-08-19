<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2 Fase 1: columna `priority` en client_provider_routing — orden en que
 * ProviderDispatchResolver (Fase 2) prueba los proveedores de un cliente al
 * despachar una venta V2 (menor = se intenta primero). Aditiva y sin efecto
 * en el flujo ACTUAL: ni ProviderResolver::allowedForClient()/
 * resolveEffectiveFor() ni CommunicationSaleService::resolveAndGuardProvider()
 * leen esta columna hoy — solo la consumirá ProviderDispatchResolver cuando
 * exista y solo para clientes en V2 (flag, Fase 4). El comportamiento de
 * despacho de ningún cliente cambia con este deploy.
 *
 * Backfill (mejor esfuerzo, sin impacto real por lo anterior): por cliente
 * activo, la fila "más general" (environment IS NULL AND sale_type IS
 * NULL — el catch-all equivalente al proveedor default efectivo del
 * cliente) queda con priority=0; si no existe tal fila, se usa la más
 * antigua (id ASC) como aproximación de "el proveedor único/original del
 * cliente". El resto conserva el DEFAULT (100).
 */
final class Version20260810120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade priority a client_provider_routing + backfill (V2 Fase 1, dispatch por prioridad)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_provider_routing ADD COLUMN IF NOT EXISTS priority SMALLINT NOT NULL DEFAULT 100');

        $this->addSql(<<<'SQL'
            UPDATE client_provider_routing cpr
            SET priority = 0
            FROM (
                SELECT DISTINCT ON (client_id) id
                FROM client_provider_routing
                WHERE is_active = TRUE
                ORDER BY client_id,
                         (environment_id IS NULL AND sale_type IS NULL) DESC,
                         id ASC
            ) AS primary_row
            WHERE cpr.id = primary_row.id
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_cpr_client_priority
                ON client_provider_routing (client_id, is_active, priority, id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_cpr_client_priority');
        $this->addSql('ALTER TABLE client_provider_routing DROP COLUMN IF EXISTS priority');
    }
}
