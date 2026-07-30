<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 1 del chequeo de saldo (ver docs/balance-check-architecture.md): el
 * chequeo de disponibilidad ahora resta a la cuenta las ventas en curso
 * (PENDING/RESERVED, que aún no generaron su BalanceOperation de débito) del
 * saldo del ledger. Ese cálculo pasa a correr en el camino caliente de toda
 * venta/recarga, así que necesita un índice propio — el compuesto existente
 * (tenant_id, created_at) no cubre el filtro por state, y el índice simple de
 * tenant_id tampoco. INCLUDE trae total_price para permitir un index-only
 * scan sin tocar la tabla.
 */
final class Version20260730080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Índice (tenant_id, state) INCLUDE (total_price) en communication_sale_info para el chequeo de saldo disponible';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX IF NOT EXISTS idx_csi_tenant_state ON communication_sale_info (tenant_id, state) INCLUDE (total_price)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_csi_tenant_state');
    }
}
