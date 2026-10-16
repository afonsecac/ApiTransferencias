<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-285: almacenes del mercado (market_warehouse), la clave de configuración del municipio por defecto y el almacén principal.
 *
 * - market_warehouse: estados borrador/activo/inactivo; dueño (unidad organizativa), centro de costo y municipio referenciados
 *   (RESTRICT) y opcionales mientras sea borrador; identificador (slug) único. La base refuerza que haya como mucho un almacén
 *   principal (índice único parcial) y que esté siempre activo (CHECK), como con el vendedor de plataforma.
 * - sys_config `market.warehouse.default_municipality`: código del municipio del almacén principal y propuesto al dar de alta
 *   otro. Se siembra con Boyeros si no existe y no pisa un valor ya puesto.
 * - Siembra el almacén principal: dueño = la unidad del vendedor de plataforma, su centro de costo principal activo y el
 *   municipio de la configuración. Si la clave apunta a un municipio inexistente o inactivo, la migración FALLA en voz alta en
 *   lugar de suponer uno.
 *
 * Debe ir después de COMR-286 (catálogo de municipios) y de COMR-103 (vendedor de plataforma).
 * Idempotente. Reversa (down): borra la tabla (y con ella los almacenes) y la clave.
 */
final class Version20261016110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_warehouse (almacenes), siembra la clave del municipio por defecto y el almacén principal de la plataforma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_warehouse_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_warehouse (
                id                    INT          NOT NULL DEFAULT nextval('market_warehouse_id_seq'),
                slug                  VARCHAR(60)  NOT NULL,
                name                  VARCHAR(160) NOT NULL,
                address               VARCHAR(300) DEFAULT NULL,
                status                VARCHAR(10)  NOT NULL,
                is_main               BOOLEAN      NOT NULL DEFAULT FALSE,
                org_unit_id           INT          DEFAULT NULL,
                cost_center_id        INT          DEFAULT NULL,
                municipality_id       INT          DEFAULT NULL,
                perm_sets_coverage    BOOLEAN      NOT NULL DEFAULT FALSE,
                perm_assembles_combos BOOLEAN      NOT NULL DEFAULT FALSE,
                perm_sees_supply_cost BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_warehouse_org_unit FOREIGN KEY (org_unit_id) REFERENCES market_org_unit (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_warehouse_cost_center FOREIGN KEY (cost_center_id) REFERENCES market_cost_center (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_warehouse_municipality FOREIGN KEY (municipality_id) REFERENCES market_municipality (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_warehouse_status CHECK (status IN ('draft', 'active', 'inactive')),
                CONSTRAINT ck_market_warehouse_main_active CHECK (NOT is_main OR status = 'active')
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_warehouse_id_seq OWNED BY market_warehouse.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_warehouse_slug ON market_warehouse (slug)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_warehouse_main ON market_warehouse (is_main) WHERE is_main');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_warehouse_org_unit ON market_warehouse (org_unit_id)');

        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), 'market.warehouse.default_municipality', 'boyeros', NOW(), NOW(), TRUE, FALSE
            WHERE NOT EXISTS (SELECT 1 FROM sys_config sc WHERE sc.property_name = 'market.warehouse.default_municipality')
        SQL);

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE
                unit_id INT;
                center_id INT;
                muni_id INT;
                muni_code TEXT;
            BEGIN
                IF EXISTS (SELECT 1 FROM market_warehouse WHERE is_main) THEN
                    RETURN;
                END IF;

                SELECT org_unit_id INTO unit_id FROM market_seller WHERE is_platform;
                IF unit_id IS NULL THEN
                    RAISE EXCEPTION 'COMR-285: no hay vendedor de plataforma: no se puede sembrar el almacén principal';
                END IF;
                SELECT id INTO center_id FROM market_cost_center WHERE org_unit_id = unit_id AND principal AND active;
                IF center_id IS NULL THEN
                    RAISE EXCEPTION 'COMR-285: la unidad de la plataforma (%) no tiene centro de costo principal activo', unit_id;
                END IF;

                SELECT property_value INTO muni_code FROM sys_config WHERE property_name = 'market.warehouse.default_municipality';
                SELECT m.id INTO muni_id
                FROM market_municipality m JOIN market_province p ON p.id = m.province_id
                WHERE m.code = muni_code AND m.is_active AND p.is_active;
                IF muni_id IS NULL THEN
                    RAISE EXCEPTION 'COMR-285: market.warehouse.default_municipality (%) no es un municipio activo del catálogo', muni_code;
                END IF;

                INSERT INTO market_warehouse (slug, name, status, is_main, org_unit_id, cost_center_id, municipality_id, created_at, updated_at)
                VALUES ('principal', 'Almacén principal', 'active', TRUE, unit_id, center_id, muni_id, NOW(), NOW())
                ON CONFLICT (slug) DO NOTHING;
            END
            $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_warehouse');
        $this->addSql('DROP SEQUENCE IF EXISTS market_warehouse_id_seq');
        $this->addSql("DELETE FROM sys_config WHERE property_name = 'market.warehouse.default_municipality'");
    }
}
