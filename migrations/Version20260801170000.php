<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * communication_product descartaba en silencio, durante el sync de
 * catálogo (CommunicationCatalogSyncService::syncProducts()), tres campos
 * que el DTO neutral de proveedor (ProviderProductDto) ya trae:
 * destinationAmount, destinationUnit y priceCurrency. Sin ellos no hay
 * forma de saber en qué moneda se acredita un producto ni de compararla
 * contra la moneda del cliente al importar el catálogo de un proveedor
 * nuevo (ver ClientCatalogImportService).
 *
 * Nullable porque los productos ETECSA ya sincronizados no tienen este
 * dato retroactivamente disponible sin volver a sincronizar.
 */
final class Version20260801170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade destination_amount/destination_unit/price_currency a communication_product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS destination_amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS destination_unit VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS price_currency VARCHAR(3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS destination_amount');
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS destination_unit');
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS price_currency');
    }
}
