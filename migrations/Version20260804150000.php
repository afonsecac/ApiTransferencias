<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Historial de corridas de scripts/sync-prod-to-staging.sh (cron mensual +
 * disparo bajo demanda desde el dashboard, ver
 * App\Controller\DashboardStagingSyncController).
 */
final class Version20260804150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea staging_sync_run (historial de corridas del sync prod->staging)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS staging_sync_run_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS staging_sync_run (
                id             INT          NOT NULL,
                status         VARCHAR(10)  NOT NULL,
                triggered_by   VARCHAR(255)     NULL,
                started_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at    TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                error_message  TEXT             NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_staging_sync_run_created_at ON staging_sync_run (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS staging_sync_run');
        $this->addSql('DROP SEQUENCE IF EXISTS staging_sync_run_id_seq');
    }
}
