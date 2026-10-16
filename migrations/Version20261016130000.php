<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-72: cobertura y precio de entrega por municipio de un almacén.
 *
 * - market_delivery_coverage: una fila por (almacén, municipio) con tarifa por kg, por L, mínimo, máximo (vacío = sin tope), prioridad
 *   y estado; `rate_mode` queda en `linear` (los tramos escalonados están preparados y no construidos). Almacén y municipio
 *   referenciados (RESTRICT). La base refuerza tarifas ≥ 0, máximo ≥ mínimo y prioridad 1..999.
 * - market_warehouse_delivery_currency: la moneda de las tarifas de un almacén (una sola por almacén); sin fila vale EUR.
 *
 * Debe ir después de COMR-285 (almacenes). Aditiva y sin efecto: las tablas nacen vacías.
 * Reversa (down): borra ambas tablas (y con ellas las coberturas).
 */
final class Version20261016130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_delivery_coverage (cobertura y tarifas de entrega por municipio) y market_warehouse_delivery_currency (moneda por almacén)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_delivery_coverage_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_delivery_coverage (
                id              INT           NOT NULL DEFAULT nextval('market_delivery_coverage_id_seq'),
                warehouse_id    INT           NOT NULL,
                municipality_id INT           NOT NULL,
                rate_mode       VARCHAR(12)   NOT NULL DEFAULT 'linear',
                rate_per_kg     NUMERIC(12,4) NOT NULL,
                rate_per_l      NUMERIC(12,4) NOT NULL,
                min_price       NUMERIC(12,4) NOT NULL,
                max_price       NUMERIC(12,4) DEFAULT NULL,
                priority        INT           NOT NULL DEFAULT 1,
                is_active       BOOLEAN       NOT NULL DEFAULT TRUE,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_delivery_coverage_warehouse FOREIGN KEY (warehouse_id) REFERENCES market_warehouse (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_delivery_coverage_municipality FOREIGN KEY (municipality_id) REFERENCES market_municipality (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_delivery_coverage_mode CHECK (rate_mode IN ('linear')),
                CONSTRAINT ck_market_delivery_coverage_rates CHECK (rate_per_kg >= 0 AND rate_per_l >= 0 AND min_price >= 0),
                CONSTRAINT ck_market_delivery_coverage_max CHECK (max_price IS NULL OR max_price >= min_price),
                CONSTRAINT ck_market_delivery_coverage_priority CHECK (priority BETWEEN 1 AND 999)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_delivery_coverage_id_seq OWNED BY market_delivery_coverage.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_delivery_coverage ON market_delivery_coverage (warehouse_id, municipality_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_delivery_coverage_municipality ON market_delivery_coverage (municipality_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_warehouse_delivery_currency (
                warehouse_id INT        NOT NULL,
                currency     VARCHAR(3) NOT NULL,
                PRIMARY KEY (warehouse_id),
                CONSTRAINT fk_market_warehouse_delivery_currency FOREIGN KEY (warehouse_id) REFERENCES market_warehouse (id) ON DELETE CASCADE
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_warehouse_delivery_currency');
        $this->addSql('DROP TABLE IF EXISTS market_delivery_coverage');
        $this->addSql('DROP SEQUENCE IF EXISTS market_delivery_coverage_id_seq');
    }
}
