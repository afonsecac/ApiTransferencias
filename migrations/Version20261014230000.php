<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-70: el producto referencia su marca (opcional) y sus categorías. `brand_id` es una columna con clave foránea a
 * market_brand (RESTRICT: una marca con productos no se borra, se desactiva) y las categorías van en la tabla de unión
 * market_product_category (borrar un producto borra sus enlaces; una categoría con productos no se borra, RESTRICT).
 * Que la marca sea del mismo vendedor del producto lo valida el dominio, no la base.
 *
 * Aditiva: los productos que ya existen quedan sin marca y sin categorías (siguen siendo válidos; solo publicar exige una
 * categoría). Reversa (down): quita la tabla de unión y la columna.
 */
final class Version20261014230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade market_product.brand_id y la tabla market_product_category (marca y categorías del producto)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS brand_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_market_product_brand') THEN
                    ALTER TABLE market_product ADD CONSTRAINT fk_market_product_brand FOREIGN KEY (brand_id) REFERENCES market_brand (id) ON DELETE RESTRICT;
                END IF;
            END $$
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_brand ON market_product (brand_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_product_category (
                product_id  INT NOT NULL,
                category_id INT NOT NULL,
                PRIMARY KEY (product_id, category_id),
                CONSTRAINT fk_market_product_category_product FOREIGN KEY (product_id) REFERENCES market_product (id) ON DELETE CASCADE,
                CONSTRAINT fk_market_product_category_category FOREIGN KEY (category_id) REFERENCES market_category (id) ON DELETE RESTRICT
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_category_category ON market_product_category (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_product_category');
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS fk_market_product_brand');
        $this->addSql('DROP INDEX IF EXISTS idx_market_product_brand');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS brand_id');
    }
}
