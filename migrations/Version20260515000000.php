<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_encrypted column to sys_config to support encrypted property values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sys_config
                ADD COLUMN IF NOT EXISTS is_encrypted BOOLEAN NOT NULL DEFAULT FALSE
        SQL);
        // Drop the DB-level default — Doctrine manages it in PHP
        $this->addSql('ALTER TABLE sys_config ALTER COLUMN is_encrypted DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sys_config DROP COLUMN IF EXISTS is_encrypted');
    }
}
