<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-274: galería de imágenes de los productos. market_product_image guarda de cada imagen su huella de archivo (con la que
 * se nombran los tamaños en el volumen), lo subido (nombre, formato, peso, dimensiones), su tipo y texto alternativo en español
 * e inglés, su posición (1..n, única por producto) y si es la referencia. Borrar un producto borra su galería (CASCADE).
 * Una restricción única (diferida, para poder permutar posiciones al reordenar) garantiza una sola imagen por posición y
 * producto; que haya una sola imagen de referencia lo impone el dominio (ProductGallery).
 *
 * También siembra los dos límites configurables desde la pantalla de configuración: market.images.max_bytes (4 MB) y
 * market.images.max_per_product (8). Aditiva y sin efecto: la tabla nace vacía.
 *
 * Reversa (down): borra la tabla y las dos configuraciones (los archivos del volumen no se tocan).
 */
final class Version20261014240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_product_image (galería de imágenes del producto) y siembra sus límites en sys_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_product_image_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_product_image (
                id            INT          NOT NULL DEFAULT nextval('market_product_image_id_seq'),
                product_id    INT          NOT NULL,
                file          VARCHAR(64)  NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime          VARCHAR(20)  NOT NULL,
                bytes         INT          NOT NULL,
                width         INT          NOT NULL,
                height        INT          NOT NULL,
                kind          VARCHAR(40)  DEFAULT NULL,
                alt_es        VARCHAR(200) DEFAULT NULL,
                alt_en        VARCHAR(200) DEFAULT NULL,
                position      INT          NOT NULL,
                is_reference  BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_product_image_product FOREIGN KEY (product_id) REFERENCES market_product (id) ON DELETE CASCADE,
                CONSTRAINT ck_market_product_image_mime CHECK (mime IN ('image/jpeg', 'image/png', 'image/webp')),
                CONSTRAINT ck_market_product_image_position CHECK (position >= 1)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_product_image_id_seq OWNED BY market_product_image.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_product_image_file ON market_product_image (file)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_image_product ON market_product_image (product_id)');
        // Reordenar cambia posiciones: la unicidad se declara DEFERRABLE para poder permutarlas dentro de una transacción.
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uniq_market_product_image_position') THEN
                    ALTER TABLE market_product_image ADD CONSTRAINT uniq_market_product_image_position UNIQUE (product_id, position) DEFERRABLE INITIALLY DEFERRED;
                END IF;
            END $$
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), v.name, v.value, NOW(), NOW(), TRUE, FALSE
            FROM (VALUES
                ('market.images.max_bytes', '4000000'),
                ('market.images.max_per_product', '8')
            ) AS v(name, value)
            WHERE NOT EXISTS (SELECT 1 FROM sys_config sc WHERE sc.property_name = v.name)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name IN ('market.images.max_bytes', 'market.images.max_per_product')");
        $this->addSql('DROP TABLE IF EXISTS market_product_image');
        $this->addSql('DROP SEQUENCE IF EXISTS market_product_image_id_seq');
    }
}
