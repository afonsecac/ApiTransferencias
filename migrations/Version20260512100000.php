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
        // Postgres cannot deserialize PHP's serialize() format, so we convert each row in PHP first.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, data_array FROM report_marked WHERE data_array IS NOT NULL'
        );

        foreach ($rows as $row) {
            $value = (string) $row['data_array'];

            // Already valid JSON (idempotent re-run guard)
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                continue;
            }

            $decoded = @unserialize($value, ['allowed_classes' => false]);
            $this->abortIf(
                $decoded === false && $value !== 'b:0;',
                sprintf('report_marked row #%d: data_array is neither valid PHP-serialized nor JSON — manual inspection required', $row['id'])
            );

            $this->connection->executeStatement(
                'UPDATE report_marked SET data_array = :json WHERE id = :id',
                ['json' => json_encode($decoded, JSON_UNESCAPED_UNICODE), 'id' => $row['id']]
            );
        }

        // All non-null values are now valid JSON — safe to change the column type.
        $this->connection->executeStatement(
            'ALTER TABLE report_marked ALTER COLUMN data_array TYPE JSON USING data_array::json'
        );
    }

    public function down(Schema $schema): void
    {
        // Revert column type to TEXT first (JSON values become their text representation).
        $this->connection->executeStatement(
            'ALTER TABLE report_marked ALTER COLUMN data_array TYPE TEXT'
        );

        // Re-serialize JSON values back to PHP serialized format for symmetry.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, data_array FROM report_marked WHERE data_array IS NOT NULL'
        );

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['data_array'], true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE report_marked SET data_array = :serialized WHERE id = :id',
                ['serialized' => serialize($decoded), 'id' => $row['id']]
            );
        }
    }
}
