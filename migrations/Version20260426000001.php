<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (
            id SERIAL NOT NULL,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at TIMESTAMPTZ NOT NULL,
            created_at TIMESTAMPTZ NOT NULL,
            revoked_at TIMESTAMPTZ DEFAULT NULL,
            origin_ip VARCHAR(50) NOT NULL,
            family VARCHAR(64) NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_refresh_token_user FOREIGN KEY (user_id) REFERENCES "user"(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX idx_refresh_token ON refresh_token (token)');
        $this->addSql('CREATE INDEX idx_refresh_token_family ON refresh_token (family)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token');
    }
}
