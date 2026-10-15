<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-275: un producto suelto del mercado también es un paquete. communication_package.market_product_id guarda el producto que
 * ofrece cada paquete del mercado (nulo en los de comunicaciones); es una referencia por id, sin clave foránea, porque el
 * producto vive en otro contexto. Único cuando existe: un producto tiene como mucho un paquete.
 *
 * Aditiva: los paquetes existentes quedan con la columna nula, sin ningún cambio de comportamiento.
 * Reversa (down): quita el índice y la columna.
 */
final class Version20261015110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_package.market_product_id (el producto del mercado que ofrece el paquete)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_package ADD COLUMN IF NOT EXISTS market_product_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_package_market_product ON communication_package (market_product_id) WHERE market_product_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_com_package_market_product');
        $this->addSql('ALTER TABLE communication_package DROP COLUMN IF EXISTS market_product_id');
    }
}
