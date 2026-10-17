<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-284: los atributos libres del producto (color, sabor, talla…) como una lista JSON de pares nombre-valor con texto por
 * idioma. Aditiva e idempotente: los productos que ya existen quedan con la lista vacía. Reversa (down): quita la columna.
 */
final class Version20261017090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade a market_product la columna attributes con los atributos libres del producto (lista JSON, vacía por defecto)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE market_product ADD COLUMN IF NOT EXISTS attributes JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS attributes');
    }
}
