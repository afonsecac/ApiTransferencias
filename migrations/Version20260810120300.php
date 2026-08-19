<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2 Fase 1: índices de cobertura en communication_product para las dos
 * consultas nuevas que hará PackageCatalogResolver/ProviderDispatchResolver
 * (Fase 2): MAX(price) agrupado por tupla de destino, y "¿este proveedor
 * tiene un producto habilitado para esta tupla?". Sin cambio de esquema —
 * solo índices, sobre columnas que ya existen y están pobladas.
 */
final class Version20260810120300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Índices de cobertura en communication_product para el catálogo/despacho V2 (Fase 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_product_destination
                ON communication_product (destination_unit, destination_amount)
                WHERE enabled = TRUE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_product_provider_destination
                ON communication_product (provider, destination_unit, destination_amount)
                WHERE enabled = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_com_product_provider_destination');
        $this->addSql('DROP INDEX IF EXISTS idx_com_product_destination');
    }
}
