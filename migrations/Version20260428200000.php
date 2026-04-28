<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft-delete columns (is_active, removed_at) to bank_card';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_card ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE bank_card ADD COLUMN IF NOT EXISTS removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bank_card.removed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_card DROP COLUMN IF EXISTS is_active');
        $this->addSql('ALTER TABLE bank_card DROP COLUMN IF EXISTS removed_at');
    }
}
