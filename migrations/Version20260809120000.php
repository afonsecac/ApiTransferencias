<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 1 del rediseño de precios/paquetes: communication_client_package deja
 * de exigir tenant y priceClientPackage. `tenant IS NULL` pasa a significar
 * "paquete referencia" (plantilla administrable de la que se copian
 * paquetes por cliente — ver PackageMaterializationService,
 * CommunicationClientPackageProvider). `product_id`/`reference_package_id`
 * son las columnas nuevas que permiten resolver proveedor/producto y
 * contrato sin depender de priceClientPackage (que ahora puede ser null).
 *
 * `product_id` se rellena por backfill desde el contrato actual
 * (price_client_package.product_id) — dato ya existente y determinista, no
 * se pierde información. Sin cambio de comportamiento: nada lee todavía
 * estas columnas nuevas (eso llega en fases 2-3).
 */
final class Version20260809120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'communication_client_package: tenant/priceClientPackage nullable, product y reference_package (Fase 1 rediseño de precios)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_client_package ALTER COLUMN tenant_id DROP NOT NULL');

        $this->addSql('ALTER TABLE communication_client_package ADD COLUMN IF NOT EXISTS product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_client_package ADD COLUMN IF NOT EXISTS reference_package_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_client_package ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE');

        // Backfill determinista: el producto del contrato actual (si lo hay).
        $this->addSql(<<<'SQL'
            UPDATE communication_client_package ccp
            SET product_id = cpp.product_id
            FROM communication_price_package cpp
            WHERE ccp.price_client_package_id = cpp.id
              AND ccp.product_id IS NULL
        SQL);

        $this->addSql('ALTER TABLE communication_client_package ALTER COLUMN price_client_package_id DROP NOT NULL');

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_client_package
                ADD CONSTRAINT FK_CCP_PRODUCT FOREIGN KEY (product_id) REFERENCES communication_product (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_client_package
                ADD CONSTRAINT FK_CCP_REFERENCE_PACKAGE FOREIGN KEY (reference_package_id) REFERENCES communication_client_package (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ccp_tenant_product ON communication_client_package (tenant_id, product_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ccp_reference ON communication_client_package (reference_package_id)');

        // Cierra la carrera de dos GET concurrentes materializando el mismo
        // paquete referencia para el mismo tenant (hoy no hay ninguna
        // protección — CommunicationClientPackageProvider hace persist+flush
        // dentro de una lectura sin lock).
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ccp_tenant_reference ON communication_client_package (tenant_id, reference_package_id) WHERE reference_package_id IS NOT NULL');
        // Un solo paquete referencia por (environment, product).
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_ccp_reference_product ON communication_client_package (environment_id, product_id) WHERE tenant_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_ccp_reference_product');
        $this->addSql('DROP INDEX IF EXISTS uniq_ccp_tenant_reference');
        $this->addSql('DROP INDEX IF EXISTS idx_ccp_reference');
        $this->addSql('DROP INDEX IF EXISTS idx_ccp_tenant_product');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT IF EXISTS FK_CCP_REFERENCE_PACKAGE');
        $this->addSql('ALTER TABLE communication_client_package DROP CONSTRAINT IF EXISTS FK_CCP_PRODUCT');
        $this->addSql('ALTER TABLE communication_client_package DROP COLUMN IF EXISTS is_active');
        $this->addSql('ALTER TABLE communication_client_package DROP COLUMN IF EXISTS reference_package_id');
        $this->addSql('ALTER TABLE communication_client_package DROP COLUMN IF EXISTS product_id');
        // price_client_package_id y tenant_id vuelven a NOT NULL solo si no
        // quedan filas nulas (paquetes referencia creados tras esta
        // migración) — se deja manual a propósito, revertir a ciegas
        // rompería cualquier referencia ya creada.
    }
}
