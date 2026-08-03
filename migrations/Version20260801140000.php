<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 3 del enrutado multi-proveedor: communication_product deja de asumir
 * un único proveedor. Hasta ahora "environment + package_id" era la clave
 * natural del upsert de catálogo (EtecsaCatalogSyncService::syncProducts()),
 * pero SOLO por convención de código — nunca hubo un índice único que lo
 * garantizara. Con un segundo proveedor, dos productos de proveedores
 * distintos con el mismo package_id colisionarían.
 *
 * Antes de crear el índice único hay que consolidar los duplicados que ya
 * existan (confirmado: SÍ los hay en datos reales). Se conserva, de cada
 * grupo duplicado, la fila con más referencias reales (price_package +
 * promotions) — normalmente la única realmente en uso — y se re-apuntan las
 * referencias de las demás antes de borrarlas. La fusión de datos no es
 * reversible; el down() solo revierte el cambio de esquema.
 */
final class Version20260801140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deduplica communication_product y añade provider/external_ref (Fase 3, catálogo multi-proveedor)';
    }

    public function up(Schema $schema): void
    {
        $dedupCte = <<<'SQL'
            WITH ref_counts AS (
                SELECT cp.id, cp.environment_id, cp.package_id,
                       (SELECT count(*) FROM communication_price_package WHERE product_id = cp.id) +
                       (SELECT count(*) FROM communication_promotions WHERE product_id = cp.id) AS refs
                FROM communication_product cp
            ),
            ranked AS (
                SELECT id, environment_id, package_id,
                       row_number() OVER (PARTITION BY environment_id, package_id ORDER BY refs DESC, id ASC) AS rn,
                       first_value(id) OVER (PARTITION BY environment_id, package_id ORDER BY refs DESC, id ASC) AS keep_id
                FROM ref_counts
            )
            SELECT id AS remove_id, keep_id FROM ranked WHERE rn > 1
        SQL;

        $this->addSql(<<<SQL
            UPDATE communication_price_package cpp
            SET product_id = dedup.keep_id
            FROM ({$dedupCte}) dedup
            WHERE cpp.product_id = dedup.remove_id
        SQL);

        $this->addSql(<<<SQL
            UPDATE communication_promotions p
            SET product_id = dedup.keep_id
            FROM ({$dedupCte}) dedup
            WHERE p.product_id = dedup.remove_id
        SQL);

        $this->addSql(<<<SQL
            DELETE FROM communication_product cp
            USING ({$dedupCte}) dedup
            WHERE cp.id = dedup.remove_id
        SQL);

        $this->addSql("ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS provider VARCHAR(20) NOT NULL DEFAULT 'ETECSA'");
        $this->addSql('ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS external_ref VARCHAR(64) DEFAULT NULL');
        $this->addSql('UPDATE communication_product SET external_ref = package_id::text WHERE external_ref IS NULL');
        $this->addSql('ALTER TABLE communication_product ALTER COLUMN external_ref SET NOT NULL');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_product_key ON communication_product (environment_id, provider, external_ref)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_com_product_key');
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS external_ref');
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS provider');
        // La fusión de duplicados no es reversible: los registros eliminados no se recrean.
    }
}
