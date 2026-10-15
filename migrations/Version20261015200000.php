<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-277: siembra el interruptor `market.ai.enabled` (asistencia con IA del catálogo), apagado. Hoy solo se lee: la creación de
 * productos con IA va aparte. Aditiva e idempotente; no cambia ningún valor ya existente.
 *
 * Reversa (down): borra la clave.
 */
final class Version20261015200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el interruptor market.ai.enabled (apagado) en sys_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), 'market.ai.enabled', '0', NOW(), NOW(), TRUE, FALSE
            WHERE NOT EXISTS (SELECT 1 FROM sys_config sc WHERE sc.property_name = 'market.ai.enabled')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name = 'market.ai.enabled'");
    }
}
