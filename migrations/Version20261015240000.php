<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-281: el producto recuerda en qué unidad se escribieron sus dimensiones (mm, cm, m, in o ft). Se guardan siempre en
 * milímetros; la unidad solo sirve para mostrárselas igual a quien edita. Aditiva e idempotente: los productos que ya existían
 * quedan con «mm», que es como se escribieron.
 *
 * Reversa (down): quita la columna.
 */
final class Version20261015240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade market_product.dimensions_unit (mm, cm, m, in o ft) con mm por defecto';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE market_product ADD COLUMN IF NOT EXISTS dimensions_unit VARCHAR(2) NOT NULL DEFAULT 'mm'");
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_market_product_dimensions_unit') THEN
                    ALTER TABLE market_product ADD CONSTRAINT ck_market_product_dimensions_unit CHECK (dimensions_unit IN ('mm', 'cm', 'm', 'in', 'ft'));
                END IF;
            END
            $$
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_dimensions_unit');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS dimensions_unit');
    }
}
