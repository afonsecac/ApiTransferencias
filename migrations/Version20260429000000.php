<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create job_position catalog table and link it to the user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS job_position (
                id         SERIAL PRIMARY KEY,
                code       VARCHAR(3)   NOT NULL,
                name       VARCHAR(100) NOT NULL,
                area       VARCHAR(20)  NOT NULL,
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT uq_job_position_code UNIQUE (code)
            )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO job_position (code, name, area, is_active, created_at, updated_at)
            VALUES
                ('CEO', 'Director General',        'MANAGEMENT', TRUE, NOW(), NOW()),
                ('CTO', 'Director Tecnologia',     'TECHNOLOGY', TRUE, NOW(), NOW()),
                ('CFO', 'Director Financiero',     'FINANCE',    TRUE, NOW(), NOW()),
                ('AS0', 'Asistente Financiero',    'FINANCE',    TRUE, NOW(), NOW()),
                ('DEV', 'Desarrollador',           'TECHNOLOGY', TRUE, NOW(), NOW()),
                ('COM', 'Area comercial y Marketing', 'MARKETING', TRUE, NOW(), NOW())
            ON CONFLICT (code) DO NOTHING
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
                ADD COLUMN IF NOT EXISTS job_position_id INT DEFAULT NULL,
                ADD CONSTRAINT fk_user_job_position
                    FOREIGN KEY (job_position_id) REFERENCES job_position (id)
                    ON DELETE SET NULL
                    DEFERRABLE INITIALLY DEFERRED
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_job_position ON "user" (job_position_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT IF EXISTS fk_user_job_position');
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS job_position_id');
        $this->addSql('DROP TABLE IF EXISTS job_position');
    }
}
