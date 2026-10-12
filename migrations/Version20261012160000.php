<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auditoría del interruptor de mercado por cuenta (ADR-001): cuándo y quién cambió account.market_enabled por
 * última vez. Sin backfill: las cuentas existentes nunca lo han tocado, así que quedan en NULL.
 */
final class Version20261012160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade account.market_changed_at y market_changed_by_id (auditoría del interruptor de mercado)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD COLUMN IF NOT EXISTS market_changed_at TIMESTAMP(0) WITHOUT TIME ZONE NULL');
        $this->addSql('ALTER TABLE account ADD COLUMN IF NOT EXISTS market_changed_by_id INT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_account_market_changed_by') THEN
                    ALTER TABLE account
                        ADD CONSTRAINT fk_account_market_changed_by FOREIGN KEY (market_changed_by_id) REFERENCES "user" (id);
                END IF;
            END
            $$
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_account_market_changed_by ON account (market_changed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP CONSTRAINT IF EXISTS fk_account_market_changed_by');
        $this->addSql('DROP INDEX IF EXISTS idx_account_market_changed_by');
        $this->addSql('ALTER TABLE account DROP COLUMN IF EXISTS market_changed_by_id');
        $this->addSql('ALTER TABLE account DROP COLUMN IF EXISTS market_changed_at');
    }
}
