<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make price_used_id nullable in communication_price_package';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_price_package ALTER COLUMN price_used_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_price_package ALTER COLUMN price_used_id SET NOT NULL');
    }
}
