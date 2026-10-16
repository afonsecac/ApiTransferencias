<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-276: gift cards propias.
 *
 * - market_gift_card_batch: un lote de emisión (producto, cantidad, caducidad, centro de costo, quién lo emitió).
 * - market_gift_card: la tarjeta, con serie única (secuencia propia), valor y moneda, PIN cifrado y su huella única, estado,
 *   caducidad, centro de costo, comprador y venta, canje (fecha y clave) y motivo de anulación. La base refuerza el estado, que
 *   el valor sea positivo y que una canjeada lleve su fecha y su clave.
 * - market_gift_card_event: la traza de cada tarjeta. INMUTABLE: un disparador rechaza modificar o borrar un hecho.
 *
 * Aditiva y sin efecto: las tablas nacen vacías. Reversa (down): borra las tres tablas y la secuencia.
 */
final class Version20261016170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_gift_card_batch, market_gift_card (PIN cifrado, serie única) y market_gift_card_event (traza inmutable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_gift_card_serial_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_gift_card_batch_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_gift_card_batch (
                id             INT          NOT NULL DEFAULT nextval('market_gift_card_batch_id_seq'),
                product_id     INT          NOT NULL,
                quantity       INT          NOT NULL,
                expires_at     DATE         DEFAULT NULL,
                cost_center_id INT          NOT NULL,
                issued_by      VARCHAR(180) NOT NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_gift_card_batch_product FOREIGN KEY (product_id) REFERENCES market_product (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_gift_card_batch_cost_center FOREIGN KEY (cost_center_id) REFERENCES market_cost_center (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_gift_card_batch_quantity CHECK (quantity BETWEEN 1 AND 1000)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_gift_card_batch_id_seq OWNED BY market_gift_card_batch.id');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_gift_card_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_gift_card (
                id             INT          NOT NULL DEFAULT nextval('market_gift_card_id_seq'),
                batch_id       INT          NOT NULL,
                product_id     INT          NOT NULL,
                serial         VARCHAR(20)  NOT NULL,
                value_minor    BIGINT       NOT NULL,
                currency       VARCHAR(3)   NOT NULL,
                pin_cipher     TEXT         NOT NULL,
                pin_hash       VARCHAR(128) NOT NULL,
                status         VARCHAR(10)  NOT NULL DEFAULT 'issued',
                expires_at     DATE         DEFAULT NULL,
                cost_center_id INT          NOT NULL,
                sold_to        VARCHAR(180) DEFAULT NULL,
                sale_ref       VARCHAR(80)  DEFAULT NULL,
                redeemed_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                redemption_ref VARCHAR(80)  DEFAULT NULL,
                cancel_reason  VARCHAR(300) DEFAULT NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_gift_card_batch FOREIGN KEY (batch_id) REFERENCES market_gift_card_batch (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_gift_card_product FOREIGN KEY (product_id) REFERENCES market_product (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_gift_card_cost_center FOREIGN KEY (cost_center_id) REFERENCES market_cost_center (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_gift_card_status CHECK (status IN ('issued', 'active', 'redeemed', 'cancelled')),
                CONSTRAINT ck_market_gift_card_value CHECK (value_minor > 0),
                CONSTRAINT ck_market_gift_card_redeemed CHECK ((status = 'redeemed') = (redeemed_at IS NOT NULL AND redemption_ref IS NOT NULL))
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_gift_card_id_seq OWNED BY market_gift_card.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_gift_card_serial ON market_gift_card (serial)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_gift_card_pin_hash ON market_gift_card (pin_hash)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_gift_card_batch ON market_gift_card (batch_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_gift_card_product ON market_gift_card (product_id)');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_gift_card_event_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_gift_card_event (
                id         INT          NOT NULL DEFAULT nextval('market_gift_card_event_id_seq'),
                card_id    INT          NOT NULL,
                type       VARCHAR(20)  NOT NULL,
                actor_name VARCHAR(180) NOT NULL,
                detail     VARCHAR(300) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_gift_card_event_card FOREIGN KEY (card_id) REFERENCES market_gift_card (id) ON DELETE RESTRICT
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_gift_card_event_id_seq OWNED BY market_gift_card_event.id');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_gift_card_event_card ON market_gift_card_event (card_id, created_at)');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION market_gift_card_event_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'market_gift_card_event es una traza inmutable: un hecho no se modifica ni se borra';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql('DROP TRIGGER IF EXISTS trg_market_gift_card_event_immutable ON market_gift_card_event');
        $this->addSql(<<<'SQL'
            CREATE TRIGGER trg_market_gift_card_event_immutable
            BEFORE UPDATE OR DELETE ON market_gift_card_event
            FOR EACH ROW EXECUTE FUNCTION market_gift_card_event_immutable()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_gift_card_event');
        $this->addSql('DROP FUNCTION IF EXISTS market_gift_card_event_immutable()');
        $this->addSql('DROP TABLE IF EXISTS market_gift_card');
        $this->addSql('DROP TABLE IF EXISTS market_gift_card_batch');
        $this->addSql('DROP SEQUENCE IF EXISTS market_gift_card_event_id_seq');
        $this->addSql('DROP SEQUENCE IF EXISTS market_gift_card_id_seq');
        $this->addSql('DROP SEQUENCE IF EXISTS market_gift_card_batch_id_seq');
        $this->addSql('DROP SEQUENCE IF EXISTS market_gift_card_serial_seq');
    }
}
