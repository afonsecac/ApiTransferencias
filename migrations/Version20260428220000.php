<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing transaction_type column to transfer (field was unmapped — never persisted)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfer ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transfer DROP COLUMN IF EXISTS transaction_type');
    }
}
