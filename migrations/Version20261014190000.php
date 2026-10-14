<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * El tipo de un producto lo dan el servicio, el subservicio y el área, no un campo aparte (T1, COMR-275/276).
 *
 * - Añade `service` y `subservice` (obligatorios) a market_product y rellena los productos que ya existían: servicio
 *   «Gift Cards» y el subservicio que correspondía a su categoría (o «Retail» si no tenían).
 * - El `area` pasa a ser un nombre del vocabulario («Supermarket»); los valores anteriores que no lo eran (`supermarket`,
 *   `retail`, `gift_cards`) se corrigen o se quitan, porque «Retail» es un subservicio y «Gift Cards» un servicio.
 * - Las columnas de la gift card dejan de llevar la categoría (ahora es el subservicio) y se renombran a `value_*`: un
 *   producto lleva contenido o valor (digital), y eso se refuerza con un CHECK.
 * - Un segundo CHECK mantiene la clasificación en el vocabulario controlado: el área solo existe bajo su subservicio.
 *
 * Reversa (down): vuelve a las columnas `gift_card_*` y quita la clasificación. Pierde el subservicio de los productos con
 * valor (la categoría se reconstruye desde él), así que es una reversa aproximada: úsala solo en desarrollo.
 */
final class Version20261014190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clasificación de market_product por servicio, subservicio y área en lugar de un tipo; columnas value_* para el valor de un producto digital';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS service VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS subservice VARCHAR(30) DEFAULT NULL');
        $this->addSql("UPDATE market_product SET service = 'Gift Cards' WHERE service IS NULL");
        // El subservicio de los productos que ya existían sale de su categoría; solo si la columna aún existe (repetible).
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = 'gift_card_category') THEN
                    UPDATE market_product SET subservice = CASE gift_card_category
                        WHEN 'gaming' THEN 'Gaming'
                        WHEN 'cash_cards' THEN 'Cash Cards'
                        WHEN 'food' THEN 'Food'
                        WHEN 'entertainment' THEN 'Entertainment'
                        WHEN 'travel_transport' THEN 'Travel & Transport'
                        ELSE 'Retail'
                    END
                    WHERE subservice IS NULL;
                END IF;
            END
            $$
        SQL);
        $this->addSql("UPDATE market_product SET subservice = 'Retail' WHERE subservice IS NULL");
        $this->addSql("UPDATE market_product SET area = 'Supermarket', subservice = 'Retail' WHERE area IN ('supermarket', 'Supermarket')");
        $this->addSql("UPDATE market_product SET area = NULL WHERE area IS NOT NULL AND area <> 'Supermarket'");
        $this->addSql('ALTER TABLE market_product ALTER COLUMN service SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN subservice SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN area TYPE VARCHAR(30)');

        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_type');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS gift_card_category');
        $this->renameColumn('gift_card_currency', 'value_currency');
        $this->renameColumn('gift_card_min_minor', 'value_min_minor');
        $this->renameColumn('gift_card_max_minor', 'value_max_minor');

        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_shape');
        $this->addSql(<<<'SQL'
            ALTER TABLE market_product ADD CONSTRAINT ck_market_product_shape CHECK (
                (value_currency IS NULL
                    AND content_base IS NOT NULL AND content_original IS NOT NULL AND content_unit IS NOT NULL
                    AND delivery_kind IS NOT NULL AND delivery_unit IS NOT NULL
                    AND value_min_minor IS NULL AND value_max_minor IS NULL)
                OR
                (value_currency IS NOT NULL AND value_min_minor > 0
                    AND (value_max_minor IS NULL OR value_max_minor > value_min_minor)
                    AND content_base IS NULL AND content_original IS NULL AND content_unit IS NULL
                    AND unit_weight_base IS NULL AND unit_volume_base IS NULL
                    AND length_mm IS NULL AND width_mm IS NULL AND height_mm IS NULL
                    AND delivery_kind IS NULL AND delivery_unit IS NULL AND barcode IS NULL)
            )
        SQL);
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_classification');
        $this->addSql(<<<'SQL'
            ALTER TABLE market_product ADD CONSTRAINT ck_market_product_classification CHECK (
                service IN ('Gift Cards')
                AND subservice IN ('Retail', 'Gaming', 'Cash Cards', 'Food', 'Entertainment', 'Travel & Transport')
                AND (area IS NULL OR (area = 'Supermarket' AND subservice = 'Retail'))
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_classification ON market_product (service, subservice, area)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_market_product_classification');
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_classification');
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_shape');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS gift_card_category VARCHAR(20) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE market_product SET gift_card_category = CASE subservice
                WHEN 'Gaming' THEN 'gaming' WHEN 'Cash Cards' THEN 'cash_cards' WHEN 'Food' THEN 'food'
                WHEN 'Entertainment' THEN 'entertainment' WHEN 'Travel & Transport' THEN 'travel_transport' ELSE 'retail'
            END
            WHERE value_currency IS NOT NULL
        SQL);
        $this->renameColumn('value_currency', 'gift_card_currency', true);
        $this->renameColumn('value_min_minor', 'gift_card_min_minor', true);
        $this->renameColumn('value_max_minor', 'gift_card_max_minor', true);
        $this->addSql("UPDATE market_product SET area = LOWER(area) WHERE area IS NOT NULL");
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS subservice');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS service');
        $this->addSql(<<<'SQL'
            ALTER TABLE market_product ADD CONSTRAINT ck_market_product_type CHECK (
                (gift_card_category IS NULL
                    AND content_base IS NOT NULL AND content_original IS NOT NULL AND content_unit IS NOT NULL
                    AND delivery_kind IS NOT NULL AND delivery_unit IS NOT NULL
                    AND gift_card_currency IS NULL AND gift_card_min_minor IS NULL AND gift_card_max_minor IS NULL)
                OR
                (gift_card_category IS NOT NULL AND gift_card_currency IS NOT NULL AND gift_card_min_minor > 0
                    AND (gift_card_max_minor IS NULL OR gift_card_max_minor > gift_card_min_minor)
                    AND content_base IS NULL AND content_original IS NULL AND content_unit IS NULL
                    AND unit_weight_base IS NULL AND unit_volume_base IS NULL
                    AND length_mm IS NULL AND width_mm IS NULL AND height_mm IS NULL
                    AND delivery_kind IS NULL AND delivery_unit IS NULL AND barcode IS NULL)
            )
        SQL);
    }

    /** Renombra una columna solo si existe con el nombre de origen: así la migración se puede repetir. */
    private function renameColumn(string $from, string $to, bool $skipExistingTarget = false): void
    {
        $this->addSql(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = '%1$s')
                   AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = '%2$s') THEN
                    ALTER TABLE market_product RENAME COLUMN %1$s TO %2$s;
                END IF;
            END
            $$
            SQL, $from, $to));
    }
}
