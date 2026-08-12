<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 3 del rediseño de precios/paquetes: backfill de datos, no de esquema.
 * Crea un paquete REFERENCIA (tenant IS NULL) por cada (environment,
 * product) que ya tenga al menos un CommunicationClientPackage materializado
 * de verdad, y re-apunta esas filas materializadas a su referencia vía
 * reference_package_id — sin esto, CommunicationClientPackageProvider caería
 * siempre a la rama legacy (plantillas en communication_price_package) para
 * clientes ya existentes hasta que ClientCatalogImportService volviera a
 * correr para ellos.
 *
 * Se excluyen deliberadamente los paquetes de promoción (tabla puente
 * communication_promotions_communication_client_package): esos son efímeros
 * y ya tienen su propio contrato congelado (price_client_package_id), no
 * deben convertirse en la plantilla de referencia de nadie.
 *
 * Hallazgo real en datos de dev: un mismo (tenant, product) puede tener
 * DECENAS de CommunicationClientPackage — CommunicationPromotionService
 * genera un tramo de precio por cada paso de amountFrom..amountTo, todos
 * sobre el mismo producto. Re-apuntar TODOS esos tramos al mismo
 * reference_package_id violaría uniq_ccp_tenant_reference (un tenant no
 * puede tener dos filas con la misma referencia). El UPDATE de abajo por
 * eso solo re-apunta cuando el tenant tiene EXACTAMENTE una fila para ese
 * producto — los tramos múltiples se quedan sin referencia, siguen
 * resolviendo su precio por el contrato congelado de siempre (regla 1 de
 * PackageSalePriceResolver), sin cambio de comportamiento.
 *
 * Fila elegida por (environment, product): la materializada más reciente
 * (created_at DESC) — es solo el origen de la estructura (benefits/tags/
 * service/destination/validity/name/description), no del precio.
 *
 * down() NO revierte los datos insertados (mismo criterio que
 * Version20260801140000 con su deduplicación): no hay forma fiable de
 * distinguir por SQL una referencia creada por este backfill de una creada
 * a mano después. Solo revertir a mano contra un respaldo si hace falta.
 */
final class Version20260809120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill de paquetes referencia desde los ClientPackage materializados existentes (Fase 3 rediseño de precios)';
    }

    public function up(Schema $schema): void
    {
        $referenceSource = <<<'SQL'
            SELECT DISTINCT ON (ccp.environment_id, ccp.product_id) ccp.*
            FROM communication_client_package ccp
            WHERE ccp.tenant_id IS NOT NULL
              AND ccp.product_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM communication_promotions_communication_client_package pp
                  WHERE pp.communication_client_package_id = ccp.id
              )
            ORDER BY ccp.environment_id, ccp.product_id, ccp.created_at DESC
        SQL;

        $this->addSql(<<<SQL
            INSERT INTO communication_client_package (
                id, tenant_id, product_id, reference_package_id, is_active,
                created_at, updated_at, active_start_at, active_end_at,
                benefits, description, name, tags, service, destination,
                validity, know_more, price_client_package_id, amount, currency,
                environment_id
            )
            SELECT
                nextval('communication_client_package_id_seq'),
                NULL, src.product_id, NULL, TRUE,
                NOW(), NOW(), src.active_start_at, src.active_end_at,
                src.benefits, src.description, src.name, src.tags, src.service, src.destination,
                src.validity, src.know_more, NULL, NULL, NULL,
                src.environment_id
            FROM ({$referenceSource}) src
            WHERE NOT EXISTS (
                SELECT 1 FROM communication_client_package r
                WHERE r.tenant_id IS NULL AND r.product_id = src.product_id
            )
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE communication_client_package m
            SET reference_package_id = r.id
            FROM communication_client_package r
            WHERE r.tenant_id IS NULL
              AND m.tenant_id IS NOT NULL
              AND m.product_id = r.product_id
              AND m.reference_package_id IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM communication_promotions_communication_client_package pp
                  WHERE pp.communication_client_package_id = m.id
              )
              AND (
                  SELECT count(*) FROM communication_client_package other
                  WHERE other.tenant_id = m.tenant_id AND other.product_id = m.product_id
              ) = 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Deliberadamente no reversible por SQL: no hay forma fiable de
        // distinguir "referencia creada por este backfill" de "referencia
        // creada a mano después" solo con datos de la tabla (mismo criterio
        // que Version20260801140000 con su deduplicación). Si hace falta
        // revertir, hazlo a mano contra un respaldo tomado antes del deploy.
    }
}
