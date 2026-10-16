<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-193/194: existencias y movimientos de stock.
 *
 * - market_stock_line: una línea por (almacén, producto o combo) con lo que hay en mano, lo reservado y el stock de seguridad. La
 *   base refuerza cantidades ≥ 0 y que lo en mano nunca baje de lo reservado.
 * - market_stock_movement: el libro de movimientos. Es INMUTABLE: un disparador rechaza modificar o borrar una fila. Lleva la
 *   cantidad con signo, lo que quedó en mano, el motivo, el comentario, la referencia, quién lo hizo y los centros de costo del
 *   propietario (vendedor del producto) y del custodio (dueño del almacén).
 *
 * Debe ir después de COMR-285 (almacenes). Aditiva y sin efecto: las tablas nacen vacías.
 * Reversa (down): borra ambas tablas (y con ellas el libro).
 */
final class Version20261016140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_stock_line (existencias por almacén y artículo) y market_stock_movement (movimientos inmutables)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_stock_line_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_stock_line (
                id           INT         NOT NULL DEFAULT nextval('market_stock_line_id_seq'),
                warehouse_id INT         NOT NULL,
                item_type    VARCHAR(10) NOT NULL,
                item_id      INT         NOT NULL,
                on_hand      INT         NOT NULL DEFAULT 0,
                reserved     INT         NOT NULL DEFAULT 0,
                safety       INT         NOT NULL DEFAULT 0,
                updated_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_stock_line_warehouse FOREIGN KEY (warehouse_id) REFERENCES market_warehouse (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_stock_line_type CHECK (item_type IN ('product', 'combo')),
                CONSTRAINT ck_market_stock_line_quantities CHECK (on_hand >= 0 AND reserved >= 0 AND safety >= 0),
                CONSTRAINT ck_market_stock_line_reserved CHECK (reserved <= on_hand)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_stock_line_id_seq OWNED BY market_stock_line.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_stock_line ON market_stock_line (warehouse_id, item_type, item_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_stock_line_item ON market_stock_line (item_type, item_id)');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_stock_movement_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_stock_movement (
                id                       INT          NOT NULL DEFAULT nextval('market_stock_movement_id_seq'),
                warehouse_id             INT          NOT NULL,
                item_type                VARCHAR(10)  NOT NULL,
                item_id                  INT          NOT NULL,
                type                     VARCHAR(20)  NOT NULL,
                quantity                 INT          NOT NULL,
                on_hand_after            INT          NOT NULL,
                reason                   VARCHAR(20)  NOT NULL,
                comment                  VARCHAR(500) NOT NULL,
                reference                VARCHAR(80)  DEFAULT NULL,
                actor_id                 INT          DEFAULT NULL,
                actor_name               VARCHAR(180) NOT NULL,
                owner_cost_center_id     INT          NOT NULL,
                custodian_cost_center_id INT          NOT NULL,
                created_at               TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_stock_movement_warehouse FOREIGN KEY (warehouse_id) REFERENCES market_warehouse (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_stock_movement_type CHECK (item_type IN ('product', 'combo')),
                CONSTRAINT ck_market_stock_movement_quantity CHECK (quantity <> 0 AND on_hand_after >= 0)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_stock_movement_id_seq OWNED BY market_stock_movement.id');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_stock_movement_warehouse ON market_stock_movement (warehouse_id, created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_stock_movement_item ON market_stock_movement (item_type, item_id, created_at)');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION market_stock_movement_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'market_stock_movement es un libro inmutable: un movimiento no se modifica ni se borra (se corrige con otro movimiento)';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS trg_market_stock_movement_immutable ON market_stock_movement');
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_market_stock_movement_immutable
            BEFORE UPDATE OR DELETE ON market_stock_movement
            FOR EACH ROW EXECUTE FUNCTION market_stock_movement_immutable()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_stock_movement');
        $this->addSql('DROP FUNCTION IF EXISTS market_stock_movement_immutable()');
        $this->addSql('DROP SEQUENCE IF EXISTS market_stock_movement_id_seq');
        $this->addSql('DROP TABLE IF EXISTS market_stock_line');
        $this->addSql('DROP SEQUENCE IF EXISTS market_stock_line_id_seq');
    }
}
