<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add DESC index on communication_sale_history.updated_at for sorted queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_sale_history_updated_at
                ON communication_sale_history (updated_at DESC)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_com_sale_history_updated_at');
    }
}
