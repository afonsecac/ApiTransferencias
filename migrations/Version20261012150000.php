<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Interruptor de mercado (ADR-001): columna account.market_enabled (apagada para todas las cuentas actuales) y
 * la clave global market.enabled en sys_config, apagada ('0'). Ninguna cuenta cambia de roles con esta migración.
 */
final class Version20261012150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade account.market_enabled (false) y siembra market.enabled=0 en sys_config (interruptor de mercado, D)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD COLUMN IF NOT EXISTS market_enabled BOOLEAN NOT NULL DEFAULT FALSE');

        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), 'market.enabled', '0', NOW(), NOW(), TRUE, FALSE
            WHERE NOT EXISTS (SELECT 1 FROM sys_config sc WHERE sc.property_name = 'market.enabled')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name = 'market.enabled'");
        $this->addSql('ALTER TABLE account DROP COLUMN IF EXISTS market_enabled');
    }
}
