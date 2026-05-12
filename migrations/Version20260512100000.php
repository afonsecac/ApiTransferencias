<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate report_marked.data_array column from PHP-serialized TEXT (DBAL Types::ARRAY) to JSON (DBAL Types::JSON)';
    }

    public function up(Schema $schema): void
    {
        // DBAL 4 removes Types::ARRAY. The column was stored as PHP-serialized text.
        // Converting to JSON: existing rows with serialized data must be migrated manually
        // before running this migration, or the column must be empty.
        $this->addSql('ALTER TABLE report_marked ALTER COLUMN data_array TYPE JSON USING data_array::json');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report_marked ALTER COLUMN data_array TYPE TEXT');
    }
}
