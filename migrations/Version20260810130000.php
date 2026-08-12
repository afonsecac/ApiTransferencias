<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2 Fase 4: communication_sale_info gana el snapshot de venta V2. Hasta
 * ahora `package_id` (FK a communication_client_package) era obligatoria —
 * una venta V2 no tiene un CommunicationClientPackage detrás, así que pasa
 * a nullable. Las columnas nuevas (todas nullable) son el snapshot que
 * CommunicationSaleService::admit() persiste en el momento de ADMITIR una
 * venta V2 (no en el despacho asíncrono, que lo lee como fuente preferente
 * — ver invokeRechargeCommunication()/executeNewSaleInfo()):
 *   - catalog_package_id: qué CommunicationPackage se vendió.
 *   - dispatch_product_id / dispatch_external_ref: qué CommunicationProduct
 *     concreto (de qué proveedor) se eligió para despachar
 *     (ProviderDispatchResolver::select()), y su externalRef ya resuelto —
 *     evita re-derivar el producto en el worker y que una recatalogación
 *     entre admisión y despacho cambie qué se envía al proveedor.
 *   - destination_amount / destination_currency: snapshot de
 *     CommunicationPackage::destinationAmount/Currency en el momento de la
 *     venta (igual criterio que communication_contract).
 *
 * `package_id` real (164+ filas legacy con ventas reales) NUNCA se toca ni
 * se borra — ver plan "Fase 6: communication_sale_info.package_id nunca se
 * borra". El down() de esta migración aborta si encuentra alguna fila V2
 * (package_id IS NULL) para no perder el historial real al revertir.
 */
final class Version20260810130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'communication_sale_info: package_id nullable + snapshot de venta V2 (catalog_package/dispatch_product/destination)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN package_id DROP NOT NULL');

        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS catalog_package_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS dispatch_product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS dispatch_external_ref VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS destination_amount DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS destination_currency VARCHAR(10) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_sale_info
                ADD CONSTRAINT FK_CSI_CATALOG_PACKAGE FOREIGN KEY (catalog_package_id) REFERENCES communication_package (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_sale_info
                ADD CONSTRAINT FK_CSI_DISPATCH_PRODUCT FOREIGN KEY (dispatch_product_id) REFERENCES communication_product (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_csi_catalog_package ON communication_sale_info (catalog_package_id)');
    }

    public function down(Schema $schema): void
    {
        $orphanCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM communication_sale_info WHERE package_id IS NULL'
        );
        if ($orphanCount > 0) {
            throw new \RuntimeException(sprintf(
                'No se puede revertir: hay %d venta(s) V2 (package_id IS NULL) que perderían su referencia de catálogo. '
                . 'Esta migración protege el historial real — revertir requiere una decisión manual explícita.',
                $orphanCount,
            ));
        }

        $this->addSql('DROP INDEX IF EXISTS idx_csi_catalog_package');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT IF EXISTS FK_CSI_DISPATCH_PRODUCT');
        $this->addSql('ALTER TABLE communication_sale_info DROP CONSTRAINT IF EXISTS FK_CSI_CATALOG_PACKAGE');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS destination_currency');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS destination_amount');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS dispatch_external_ref');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS dispatch_product_id');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS catalog_package_id');

        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN package_id SET NOT NULL');
    }
}
