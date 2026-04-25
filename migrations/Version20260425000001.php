<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add priority column to communication_promotions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE communication_promotions ADD priority VARCHAR(3) NOT NULL DEFAULT '999'");
        $this->addSql('CREATE INDEX idx_com_promotion_priority ON communication_promotions (priority)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_com_promotion_priority');
        $this->addSql('ALTER TABLE communication_promotions DROP priority');
    }
}
