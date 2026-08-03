<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade `is_mobile_or_internet_service` a communication_product: distingue
 * si un producto es telefonía móvil o Internet (lo único elegible para un
 * enrutado con saleType='recharge') de cualquier otra cosa que un proveedor
 * pueda entregar como si fuera una recarga directa (gift cards, comida,
 * SIM/equipos físicos...). Default true por compatibilidad con las filas
 * históricas de ETECSA, que es exclusivamente telefonía móvil.
 */
final class Version20260802180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_product.is_mobile_or_internet_service (default true)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS is_mobile_or_internet_service BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS is_mobile_or_internet_service');
    }
}
