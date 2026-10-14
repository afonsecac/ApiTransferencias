<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vendedores del mercado y vendedor de plataforma (COMR-103).
 *
 * - Crea market_seller: cada vendedor cuelga de una unidad organizativa (una como mucho) y de uno de sus centros de
 *   costo; el identificador (slug) es único. La base refuerza que haya como mucho un vendedor de plataforma (índice
 *   único parcial) y que nunca esté desactivado (CHECK).
 * - Siembra el vendedor de plataforma colgando de, por este orden: la unidad que ya tenga el tipo «platform»; la
 *   unidad de Comremit Solutions SL (ES, B10583565, la empresa dueña de la plataforma, que COMR-272 dejó como
 *   comprador); o una unidad provisional «Plataforma» editable desde la pantalla de unidades. A la unidad elegida le
 *   añade los tipos «platform» y «seller» sin quitarle los que tenga, y si no tiene centro de costo principal activo
 *   le crea el «PRINCIPAL».
 *
 * Idempotente: no duplica la tabla, los índices, la unidad provisional, los centros ni el vendedor.
 *
 * Reversa (down): borra la tabla de vendedores. La unidad que se eligió para la plataforma conserva los tipos
 * añadidos: son datos válidos y volver a subir la migración los reutiliza.
 */
final class Version20261014160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_seller (vendedores del mercado) y siembra el vendedor de plataforma sobre su unidad organizativa';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_seller_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_seller (
                id             INT          NOT NULL DEFAULT nextval('market_seller_id_seq'),
                org_unit_id    INT          NOT NULL,
                cost_center_id INT          NOT NULL,
                slug           VARCHAR(60)  NOT NULL,
                name           VARCHAR(160) NOT NULL,
                is_platform    BOOLEAN      NOT NULL DEFAULT FALSE,
                is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_seller_org_unit FOREIGN KEY (org_unit_id) REFERENCES market_org_unit (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_seller_cost_center FOREIGN KEY (cost_center_id) REFERENCES market_cost_center (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_seller_platform_active CHECK (NOT is_platform OR is_active)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_seller_id_seq OWNED BY market_seller.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_seller_slug ON market_seller (slug)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_seller_org_unit ON market_seller (org_unit_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_seller_platform ON market_seller (is_platform) WHERE is_platform');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_seller_cost_center ON market_seller (cost_center_id)');

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                unit_id INT;
                center_id INT;
                unit_name TEXT;
            BEGIN
                IF EXISTS (SELECT 1 FROM market_seller WHERE is_platform) THEN
                    RETURN;
                END IF;

                SELECT id INTO unit_id FROM market_org_unit WHERE types::jsonb @> '"platform"'::jsonb ORDER BY id LIMIT 1;
                IF unit_id IS NULL THEN
                    SELECT id INTO unit_id FROM market_org_unit WHERE country_code = 'ES' AND tax_id = 'B10583565';
                END IF;
                IF unit_id IS NULL THEN
                    INSERT INTO market_org_unit (name, legal_name, country_code, tax_id, address, billing_email, types, fiscal_data, created_at, updated_at)
                    VALUES ('Plataforma', 'Plataforma', 'ES', 'PLATAFORMA', 'Sin dirección registrada', 'facturacion@invalid.example', '["platform","seller"]', '{}', NOW(), NOW())
                    ON CONFLICT (country_code, tax_id) DO NOTHING;
                    SELECT id INTO unit_id FROM market_org_unit WHERE country_code = 'ES' AND tax_id = 'PLATAFORMA';
                END IF;

                UPDATE market_org_unit
                SET types = (
                        SELECT COALESCE(json_agg(t ORDER BY t), '[]'::json)
                        FROM (SELECT DISTINCT t FROM jsonb_array_elements_text(types::jsonb || '["platform","seller"]'::jsonb) AS t) AS all_types
                    ),
                    updated_at = NOW()
                WHERE id = unit_id AND NOT (types::jsonb @> '["platform","seller"]'::jsonb);

                SELECT id INTO center_id FROM market_cost_center WHERE org_unit_id = unit_id AND principal AND active;
                IF center_id IS NULL THEN
                    UPDATE market_cost_center SET principal = FALSE WHERE org_unit_id = unit_id AND principal;
                    INSERT INTO market_cost_center (org_unit_id, code, name, principal, active, created_at)
                    SELECT unit_id, 'PRINCIPAL', left(name, 120), TRUE, TRUE, NOW() FROM market_org_unit WHERE id = unit_id
                    ON CONFLICT (org_unit_id, code) DO UPDATE SET principal = TRUE, active = TRUE;
                    SELECT id INTO center_id FROM market_cost_center WHERE org_unit_id = unit_id AND principal;
                END IF;

                SELECT name INTO unit_name FROM market_org_unit WHERE id = unit_id;
                INSERT INTO market_seller (org_unit_id, cost_center_id, slug, name, is_platform, is_active, created_at, updated_at)
                VALUES (unit_id, center_id, 'plataforma', left(unit_name, 160), TRUE, TRUE, NOW(), NOW())
                ON CONFLICT DO NOTHING;
            END
            $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_seller');
        $this->addSql('DROP SEQUENCE IF EXISTS market_seller_id_seq');
    }
}
