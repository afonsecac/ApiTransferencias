<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft-delete column (removed_at) to sender';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sender ADD COLUMN IF NOT EXISTS removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN sender.removed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sender DROP COLUMN IF EXISTS removed_at');
    }
}
