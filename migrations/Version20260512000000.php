<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-factor authentication fields to the user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                ADD COLUMN IF NOT EXISTS two_factor_secret                    VARCHAR(255) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_enabled                   BOOLEAN      NOT NULL DEFAULT FALSE,
                ADD COLUMN IF NOT EXISTS two_factor_pending_token             VARCHAR(64)  DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_pending_token_expires_at  TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_email_code                VARCHAR(6)   DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS two_factor_email_code_expires_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                DROP COLUMN IF EXISTS two_factor_secret,
                DROP COLUMN IF EXISTS two_factor_enabled,
                DROP COLUMN IF EXISTS two_factor_pending_token,
                DROP COLUMN IF EXISTS two_factor_pending_token_expires_at,
                DROP COLUMN IF EXISTS two_factor_email_code,
                DROP COLUMN IF EXISTS two_factor_email_code_expires_at
        SQL);
    }
}
