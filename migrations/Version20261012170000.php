<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-260, paso 1 del despliegue en dos pasos: columnas next_check_at y check_attempts en la venta, con
 * backfill de next_check_at para las PENDING existentes. El código de este paso solo las escribe; la
 * consulta horaria no las lee hasta el paso 2, así que revertir es tan simple como volver al código anterior.
 */
final class Version20261012170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_sale_info.next_check_at y check_attempts (consulta de estado con espera creciente) con backfill de las PENDING';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS next_check_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS check_attempts INT NOT NULL DEFAULT 0');
        // Sin esto, una consulta por next_check_at <= ahora dejaría de ver las ventas que ya estaban pendientes.
        $this->addSql("UPDATE communication_sale_info SET next_check_at = NOW() WHERE next_check_at IS NULL AND state = 'Pending' AND state_process = 'Pending'");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_csi_next_check ON communication_sale_info (state, state_process, next_check_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_csi_next_check');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS check_attempts');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS next_check_at');
    }
}
