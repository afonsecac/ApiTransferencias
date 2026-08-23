<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 3 del rediseño "contratos por categoría" (ver
 * /home/alex/.claude/plans/immutable-knitting-candle.md): `service_key`
 * pasa a formar parte de la IDENTIDAD del contrato — el índice único
 * `uniq_com_contract_open_per_tenant_amount` (tenant, monto, moneda) gana
 * `service_key` como cuarta columna, así que dos paquetes que comparten
 * tupla monto/moneda pero difieren en categoría ahora producen DOS
 * contratos abiertos, no uno solo mezclado (`CommunicationContractService::
 * upsertContract()`/`findOpenContract()` y `CommunicationContract::
 * addPackage()`, este último ya actualizados en código para exigirlo).
 *
 * SEGURA por construcción, sin backfill ni detección de conflictos: el
 * índice viejo (tenant, monto, moneda) YA garantizaba unicidad sobre un
 * subconjunto estricto de estas columnas — agregar una columna más a un
 * índice único NUNCA puede introducir una violación donde antes no la
 * había (el nuevo índice es estrictamente MENOS restrictivo en cuántas
 * filas pueden coincidir, no más). Si por algún motivo la hipótesis
 * estuviera mal, `CREATE UNIQUE INDEX` fallaría igual y la migración
 * abortaría (transactional: true en doctrine_migrations.yaml).
 */
final class Version20260823130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contratos por categoría (Fase 3): service_key pasa a formar parte del índice único de contrato abierto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_com_contract_open_per_tenant_amount');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_contract_open_per_tenant_amount
                ON communication_contract (tenant_id, destination_amount, destination_currency, service_key)
                NULLS NOT DISTINCT
                WHERE end_at IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_com_contract_open_per_tenant_amount');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_contract_open_per_tenant_amount
                ON communication_contract (tenant_id, destination_amount, destination_currency)
                NULLS NOT DISTINCT
                WHERE end_at IS NULL
        SQL);
    }
}
