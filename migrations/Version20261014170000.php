<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Producto base del mercado (COMR-273): tabla market_product.
 *
 * Las cantidades se guardan en la unidad base (g, ml, unidades) junto con el valor y la unidad originales, para mostrarlas
 * como las introdujo el vendedor (COMR-141). Los totales de peso y volumen son derivados y se guardan para poder ordenar y
 * calcular la relación precio/peso (COMR-165). El sku es único; el vendedor es obligatorio y referenciado (RESTRICT).
 *
 * Aditiva y sin efecto: la tabla nace vacía y nada la usa todavía. Marca y categorías llegan con COMR-70.
 *
 * Reversa (down): borra la tabla (y con ella los productos).
 */
final class Version20261014170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_product (producto base del mercado con unidades de medida, estado y textos por idioma)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_product_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_product (
                id                   INT           NOT NULL DEFAULT nextval('market_product_id_seq'),
                sku                  VARCHAR(64)   NOT NULL,
                seller_id            INT           NOT NULL,
                status               VARCHAR(12)   NOT NULL,
                area                 VARCHAR(20)   DEFAULT NULL,
                texts                JSON          NOT NULL,
                content_base         NUMERIC(14,3) NOT NULL,
                content_original     NUMERIC(14,4) NOT NULL,
                content_unit         VARCHAR(8)    NOT NULL,
                unit_weight_base     NUMERIC(14,3) DEFAULT NULL,
                unit_weight_original NUMERIC(14,4) DEFAULT NULL,
                unit_weight_unit     VARCHAR(8)    DEFAULT NULL,
                unit_volume_base     NUMERIC(14,3) DEFAULT NULL,
                unit_volume_original NUMERIC(14,4) DEFAULT NULL,
                unit_volume_unit     VARCHAR(8)    DEFAULT NULL,
                length_mm            NUMERIC(10,2) DEFAULT NULL,
                width_mm             NUMERIC(10,2) DEFAULT NULL,
                height_mm            NUMERIC(10,2) DEFAULT NULL,
                delivery_kind        VARCHAR(8)    NOT NULL,
                delivery_unit        VARCHAR(8)    NOT NULL,
                barcode              VARCHAR(14)   DEFAULT NULL,
                total_weight_g       NUMERIC(16,3) DEFAULT NULL,
                total_volume_ml      NUMERIC(16,3) DEFAULT NULL,
                created_at           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_product_seller FOREIGN KEY (seller_id) REFERENCES market_seller (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_product_status CHECK (status IN ('draft', 'published', 'withdrawn')),
                CONSTRAINT ck_market_product_content_positive CHECK (content_base > 0),
                CONSTRAINT ck_market_product_delivery_kind CHECK (delivery_kind IN ('weight', 'volume'))
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_product_id_seq OWNED BY market_product.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_product_sku ON market_product (sku)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_seller ON market_product (seller_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_status ON market_product (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_product');
        $this->addSql('DROP SEQUENCE IF EXISTS market_product_id_seq');
    }
}
