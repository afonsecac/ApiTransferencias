<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace full unique constraints with partial unique indexes (WHERE removed_at IS NULL) on sender and beneficiary';
    }

    public function up(Schema $schema): void
    {
        // sender: drop full unique constraints, create partial ones excluding soft-deleted rows
        $this->addSql('ALTER TABLE sender DROP CONSTRAINT IF EXISTS unique_identification_sender');
        $this->addSql('ALTER TABLE sender DROP CONSTRAINT IF EXISTS unique__rebus_identification_sender');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_identification_sender ON sender (identification_type, identification) WHERE removed_at IS NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique__rebus_identification_sender ON sender (rebus_sender_id, identification) WHERE removed_at IS NULL');

        // beneficiary: drop full unique constraint, create partial one
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT IF EXISTS unique_beneficiary_by_environment');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_beneficiary_by_environment ON beneficiary (identification_number, environment_id) WHERE remove_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS unique_identification_sender');
        $this->addSql('DROP INDEX IF EXISTS unique__rebus_identification_sender');
        $this->addSql('ALTER TABLE sender ADD CONSTRAINT unique_identification_sender UNIQUE (identification_type, identification)');
        $this->addSql('ALTER TABLE sender ADD CONSTRAINT unique__rebus_identification_sender UNIQUE (rebus_sender_id, identification)');

        $this->addSql('DROP INDEX IF EXISTS unique_beneficiary_by_environment');
        $this->addSql('ALTER TABLE beneficiary ADD CONSTRAINT unique_beneficiary_by_environment UNIQUE (identification_number, environment_id)');
    }
}
