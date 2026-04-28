<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428140304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add compound indexes on frequently-filtered columns';
    }

    public function up(Schema $schema): void
    {
        // communication_client_package: filtrado por tenant + fechas de actividad
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ccp_tenant_dates ON communication_client_package (tenant_id, active_start_at, active_end_at)');

        // communication_price_package: filtrado por producto + estado activo
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cpp_product_active ON communication_price_package (product_id, is_active)');

        // communication_price_package: filtrado por tenant
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cpp_tenant ON communication_price_package (tenant_id)');

        // communication_sale_info: filtrado por tenant + fecha de creación (queries de dashboard)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_csi_tenant_created ON communication_sale_info (tenant_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ccp_tenant_dates');
        $this->addSql('DROP INDEX IF EXISTS idx_cpp_product_active');
        $this->addSql('DROP INDEX IF EXISTS idx_cpp_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_csi_tenant_created');
    }
}
