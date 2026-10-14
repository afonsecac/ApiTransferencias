<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gift cards como producto digital (COMR-276, sobre COMR-273): el contenido y la base de tarifa de la entrega dejan de ser
 * obligatorios en la base y se añaden las columnas propias de la gift card (categoría, moneda y valor fijo o rango en
 * unidades menores). Un CHECK obliga a que cada producto sea una cosa o la otra: físico (con contenido y base de tarifa) o
 * gift card (con categoría, moneda y valor, y sin nada de lo físico).
 *
 * Aditiva y compatible: los productos que ya existen son físicos y cumplen el CHECK; no cambia ningún dato.
 *
 * Reversa (down): quita el CHECK y las columnas de gift card y vuelve a exigir el contenido y la base de tarifa. Falla si ya
 * hay gift cards guardadas: antes hay que borrarlas.
 */
final class Version20261014180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite productos digitales (gift cards) en market_product: columnas de gift card y contenido/entrega opcionales con CHECK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_base DROP NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_original DROP NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_unit DROP NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN delivery_kind DROP NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN delivery_unit DROP NOT NULL');

        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS gift_card_category VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS gift_card_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS gift_card_min_minor BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS gift_card_max_minor BIGINT DEFAULT NULL');

        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_type');
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

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_type');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS gift_card_max_minor');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS gift_card_min_minor');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS gift_card_currency');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS gift_card_category');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN delivery_unit SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN delivery_kind SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_unit SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_original SET NOT NULL');
        $this->addSql('ALTER TABLE market_product ALTER COLUMN content_base SET NOT NULL');
    }
}
