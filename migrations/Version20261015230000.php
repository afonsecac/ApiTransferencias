<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-281: las cantidades del producto pasan de gramos y mililitros a kilogramos y litros, con 6 decimales (las columnas
 * pasan de NUMERIC(14,3)/(16,3) a NUMERIC(17,6)/(19,6): lo que antes eran 3 decimales de gramo caben ahora exactos en kilogramos).
 *
 * - Se dividen entre 1000 content_base (solo si el contenido es de peso o de volumen: el de unidades no cambia),
 *   unit_weight_base, unit_volume_base y los totales.
 * - total_weight_g pasa a total_weight_base y total_volume_ml a total_volume_base: la unidad de la columna la dice ahora la
 *   configuración (market.measure.*), no su nombre.
 * - Se mantienen las unidades originales, las dimensiones en mm y la base de tarifa.
 *
 * Idempotente: toda la conversión corre solo si la columna total_weight_g todavía existe, así que repetirla no divide dos veces.
 * El código nuevo no puede leer las columnas viejas ni al revés: se despliega con el código de esta versión.
 *
 * Reversa (down): multiplica por 1000 y devuelve los nombres y la escala de 3 decimales; los productos creados después con
 * cantidades de menos de un gramo perderían esos decimales.
 */
final class Version20261015230000 extends AbstractMigration
{
    private const PHYSICAL_UNITS = "'g', 'kg', 'oz', 'lb', 't', 'ml', 'l', 'fl_oz', 'gal'";

    public function getDescription(): string
    {
        return 'Pasa las cantidades de market_product de g/ml a kg/l con 6 decimales y renombra los totales a total_weight_base y total_volume_base';
    }

    public function up(Schema $schema): void
    {
        $physical = self::PHYSICAL_UNITS;
        $this->addSql(<<<SQL
            DO \$\$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = 'total_weight_g') THEN
                    ALTER TABLE market_product ALTER COLUMN content_base TYPE NUMERIC(17,6);
                    ALTER TABLE market_product ALTER COLUMN unit_weight_base TYPE NUMERIC(17,6);
                    ALTER TABLE market_product ALTER COLUMN unit_volume_base TYPE NUMERIC(17,6);
                    ALTER TABLE market_product ALTER COLUMN total_weight_g TYPE NUMERIC(19,6);
                    ALTER TABLE market_product ALTER COLUMN total_volume_ml TYPE NUMERIC(19,6);

                    UPDATE market_product SET content_base = content_base / 1000 WHERE content_unit IN ($physical);
                    UPDATE market_product SET unit_weight_base = unit_weight_base / 1000 WHERE unit_weight_base IS NOT NULL;
                    UPDATE market_product SET unit_volume_base = unit_volume_base / 1000 WHERE unit_volume_base IS NOT NULL;
                    UPDATE market_product SET total_weight_g = total_weight_g / 1000 WHERE total_weight_g IS NOT NULL;
                    UPDATE market_product SET total_volume_ml = total_volume_ml / 1000 WHERE total_volume_ml IS NOT NULL;

                    ALTER TABLE market_product RENAME COLUMN total_weight_g TO total_weight_base;
                    ALTER TABLE market_product RENAME COLUMN total_volume_ml TO total_volume_base;
                END IF;
            END
            \$\$
            SQL);
    }

    public function down(Schema $schema): void
    {
        $physical = self::PHYSICAL_UNITS;
        $this->addSql(<<<SQL
            DO \$\$
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = 'total_weight_base')
                   AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = 'total_weight_g') THEN
                    UPDATE market_product SET content_base = content_base * 1000 WHERE content_unit IN ($physical);
                    UPDATE market_product SET unit_weight_base = unit_weight_base * 1000 WHERE unit_weight_base IS NOT NULL;
                    UPDATE market_product SET unit_volume_base = unit_volume_base * 1000 WHERE unit_volume_base IS NOT NULL;
                    UPDATE market_product SET total_weight_base = total_weight_base * 1000 WHERE total_weight_base IS NOT NULL;
                    UPDATE market_product SET total_volume_base = total_volume_base * 1000 WHERE total_volume_base IS NOT NULL;

                    ALTER TABLE market_product RENAME COLUMN total_weight_base TO total_weight_g;
                    ALTER TABLE market_product RENAME COLUMN total_volume_base TO total_volume_ml;

                    ALTER TABLE market_product ALTER COLUMN content_base TYPE NUMERIC(14,3);
                    ALTER TABLE market_product ALTER COLUMN unit_weight_base TYPE NUMERIC(14,3);
                    ALTER TABLE market_product ALTER COLUMN unit_volume_base TYPE NUMERIC(14,3);
                    ALTER TABLE market_product ALTER COLUMN total_weight_g TYPE NUMERIC(16,3);
                    ALTER TABLE market_product ALTER COLUMN total_volume_ml TYPE NUMERIC(16,3);
                END IF;
            END
            \$\$
            SQL);
    }
}
