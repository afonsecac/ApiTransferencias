<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Trazabilidad de conversión de moneda en CommunicationPricePackage: qué
 * tasa (y de qué fecha) se aplicó al convertir el costo mayorista del
 * proveedor a la moneda del cliente (ver ClientCatalogImportService). Sin
 * esto no había forma de saber, después de importado, qué tasa se usó para
 * un paquete concreto.
 *
 * Snapshot inmutable (mismo patrón que communication_sale_info.provider):
 * se guarda el valor de la tasa en el momento de la conversión, no una FK a
 * currency_exchange_rate — así no depende de que esa fila histórica exista
 * para siempre. Nulo cuando no hizo falta convertir (misma moneda) o la
 * conversión falló (se usó el costo sin convertir, ver knowMore).
 *
 * auto_managed marca las filas creadas por ClientCatalogImportService (no
 * las creadas/editadas a mano por un admin desde el dashboard): el refresco
 * periódico (ProviderCatalogRefreshService, cada 6h) SOLO toca filas con
 * auto_managed=true — nunca sobrescribe un precio que un admin fijó a mano.
 */
final class Version20260801190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade conversion_rate/conversion_rate_date/auto_managed a communication_price_package';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS conversion_rate DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS conversion_rate_date DATE DEFAULT NULL');
        $this->addSql("ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS auto_managed BOOLEAN NOT NULL DEFAULT FALSE");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_com_price_package_auto_managed ON communication_price_package (auto_managed) WHERE auto_managed = TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_com_price_package_auto_managed');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS conversion_rate');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS conversion_rate_date');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS auto_managed');
    }
}
