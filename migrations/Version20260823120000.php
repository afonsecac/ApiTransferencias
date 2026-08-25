<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 1 del rediseño "contratos por categoría" (ver
 * /home/alex/.claude/plans/immutable-knitting-candle.md): agrega
 * clasificación service/subservice a CommunicationContract (columnas
 * planas `service_name`/`subservice_name`, no JSON — ver docblock de la
 * entidad) y la columna derivada `service_key` a ambas entidades
 * (CommunicationContract y CommunicationPackage), para poder
 * filtrar/indexar por categoría en SQL sin queries JSON (sin precedente en
 * este repo).
 *
 * ESTA MIGRACIÓN NO CAMBIA COMPORTAMIENTO VISIBLE: no toca el índice único
 * de "contrato abierto" (`uniq_com_contract_open_per_tenant_amount`, sigue
 * siendo solo por tenant+monto+moneda) ni ningún lookup usado por
 * PackageCatalogResolver — eso es Fase 3, deliberadamente separada porque
 * cambia qué contrato termina cubriendo qué paquete (dato, no solo
 * comportamiento de lectura).
 *
 * Backfill de `communication_package.service_key`: derivado del JSON
 * `service` existente con la MISMA regla de normalización que
 * ServiceCategoryKey::of() en PHP (trim, SIN uppercase — ver docblock de
 * esa clase sobre por qué evitar case-folding en SQL).
 *
 * Backfill de `communication_contract.service_name/subservice_name`:
 * copiado de CUALQUIERA de sus paquetes vinculados. Antes de hacerlo, esta
 * migración DETECTA explícitamente si algún contrato tiene paquetes de
 * categorías distintas entre sí (o ningún paquete vinculado) y ABORTA con
 * un mensaje claro en vez de elegir en silencio cuál categoría usar —
 * confirmado con SQL real contra la BD antes de escribir esta migración:
 * 0 contratos existentes mezclan categorías hoy, así que en un entorno
 * sano este chequeo no debería disparar nunca.
 */
final class Version20260823120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contratos por categoría (Fase 1): service/subservice + service_key en CommunicationContract y CommunicationPackage';
    }

    public function up(Schema $schema): void
    {
        // ── communication_package: backfill directo, sin riesgo de conflicto ──
        $this->addSql('ALTER TABLE communication_package ADD COLUMN IF NOT EXISTS service_key VARCHAR(191) NOT NULL DEFAULT \'|\'');
        $this->addSql(<<<'SQL'
            UPDATE communication_package
            SET service_key = trim(coalesce(service->>'name', '')) || '|' || trim(coalesce(service->'subservice'->>'name', ''))
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_com_package_service_key ON communication_package (service_key)');

        // ── communication_contract: columnas nuevas ──
        $this->addSql('ALTER TABLE communication_contract ADD COLUMN IF NOT EXISTS service_name VARCHAR(255)');
        $this->addSql('ALTER TABLE communication_contract ADD COLUMN IF NOT EXISTS subservice_name VARCHAR(255)');
        $this->addSql('ALTER TABLE communication_contract ADD COLUMN IF NOT EXISTS service_key VARCHAR(191) NOT NULL DEFAULT \'|\'');

        // Detección defensiva ANTES de backfillear: aborta si algún
        // contrato tiene paquetes de más de una categoría distinta entre
        // sí. Con datos sanos esto no debería disparar nunca (verificado
        // contra la BD real antes de escribir esta migración).
        //
        // OJO: $this->connection->fetchAllAssociative() corre DE INMEDIATO,
        // mientras que los addSql() de arriba quedan en cola y se ejecutan
        // recién cuando up() termina — así que esta consulta NO puede
        // depender todavía de communication_package.service_key (esa
        // columna aún "no existe" desde el punto de vista de esta lectura
        // inmediata). Por eso deriva la categoría inline desde el JSON
        // crudo, con la misma expresión que el backfill de arriba.
        $mixed = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT c.id
            FROM communication_contract c
            JOIN communication_contract_package ccp ON ccp.communication_contract_id = c.id
            JOIN communication_package p ON p.id = ccp.communication_package_id
            GROUP BY c.id
            HAVING count(DISTINCT (
                trim(coalesce(p.service->>'name', '')) || '|' || trim(coalesce(p.service->'subservice'->>'name', ''))
            )) > 1
        SQL);
        if ($mixed !== []) {
            $ids = implode(', ', array_column($mixed, 'id'));
            throw new \RuntimeException(
                "Migración abortada: los contratos [{$ids}] tienen paquetes vinculados de más de una categoría (service/subservice) " .
                'distinta entre sí — el backfill automático no puede elegir una sola categoría por contrato en este caso. ' .
                'Resolver manualmente (dividir el contrato o alinear la categoría de sus paquetes) antes de reintentar esta migración.'
            );
        }

        // Backfill: copiar la categoría de CUALQUIERA de los paquetes
        // vinculados (ya descartamos mezcla arriba, así que da igual
        // cuál). DISTINCT ON con paquete de menor id para ser determinista.
        // Contratos SIN ningún paquete vinculado quedan en '|' (categoría
        // vacía) — no es un error, es un contrato "huérfano" ya posible hoy.
        $this->addSql(<<<'SQL'
            UPDATE communication_contract c
            SET service_name = sub.service_name,
                subservice_name = sub.subservice_name,
                service_key = sub.service_key
            FROM (
                SELECT DISTINCT ON (ccp.communication_contract_id)
                    ccp.communication_contract_id,
                    p.service->>'name' AS service_name,
                    p.service->'subservice'->>'name' AS subservice_name,
                    p.service_key
                FROM communication_contract_package ccp
                JOIN communication_package p ON p.id = ccp.communication_package_id
                ORDER BY ccp.communication_contract_id, p.id ASC
            ) sub
            WHERE c.id = sub.communication_contract_id
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_com_contract_service_key ON communication_contract (service_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_com_contract_service_key');
        $this->addSql('ALTER TABLE communication_contract DROP COLUMN IF EXISTS service_key');
        $this->addSql('ALTER TABLE communication_contract DROP COLUMN IF EXISTS subservice_name');
        $this->addSql('ALTER TABLE communication_contract DROP COLUMN IF EXISTS service_name');

        $this->addSql('DROP INDEX IF EXISTS idx_com_package_service_key');
        $this->addSql('ALTER TABLE communication_package DROP COLUMN IF EXISTS service_key');
    }
}
