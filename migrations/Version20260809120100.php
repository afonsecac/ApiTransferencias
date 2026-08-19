<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 1 del rediseño de precios/paquetes: communication_price_package se
 * reconvierte en la entidad de CONTRATO de precio (ver
 * PackageSalePriceResolver, PackageContractService). No se crea tabla
 * nueva: las filas existentes con tenant ya son contratos de facto
 * (negociados a mano vía PackagePriceService o generados por promociones);
 * el backfill `is_contract = NOT auto_managed` es lo que distingue esos
 * contratos reales de los espejos automáticos del costo mayorista que crea
 * ClientCatalogImportService — y es precisamente lo que corrige el bug de
 * precio rancio documentado en el plan (esas filas dejan de ser "contrato"
 * y el precio pasa a resolverse contra CommunicationProduct vivo en la
 * Fase 2, cuando se cablee PackageSalePriceResolver).
 *
 * reference_package_id sustituye a product_id como clave real de búsqueda
 * de contrato (junto con tenant_id) — se deja product_id sin tocar, sigue
 * siendo el snapshot de costo mayorista que ya usaba
 * ProviderCatalogRefreshService.
 */
final class Version20260809120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'communication_price_package: columnas de contrato (is_contract, contract_mode, reference_package, base_price/currency, rate_value) (Fase 1 rediseño de precios)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS is_contract BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS contract_mode VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS reference_package_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS base_price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS base_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_price_package ADD COLUMN IF NOT EXISTS rate_value DOUBLE PRECISION DEFAULT NULL');

        $this->addSql('UPDATE communication_price_package SET is_contract = FALSE WHERE auto_managed = TRUE');
        $this->addSql("UPDATE communication_price_package SET contract_mode = 'FIXED' WHERE is_contract = TRUE AND contract_mode IS NULL");

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_price_package
                ADD CONSTRAINT FK_CPP_REFERENCE_PACKAGE FOREIGN KEY (reference_package_id) REFERENCES communication_client_package (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cpp_contract_lookup ON communication_price_package (tenant_id, reference_package_id, is_contract, is_active)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_cpp_contract_lookup');
        $this->addSql('ALTER TABLE communication_price_package DROP CONSTRAINT IF EXISTS FK_CPP_REFERENCE_PACKAGE');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS rate_value');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS base_currency');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS base_price');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS reference_package_id');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS contract_mode');
        $this->addSql('ALTER TABLE communication_price_package DROP COLUMN IF EXISTS is_contract');
    }
}
