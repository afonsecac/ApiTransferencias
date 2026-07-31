<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 1 del enrutado multi-proveedor de recargas/paquetes: snapshot del
 * proveedor en communication_sale_info y semillas de configuración global.
 *
 * En esta fase solo ETECSA está registrado (ver App\Provider\ProviderResolver),
 * así que el backfill es exacto: no existe otro proveedor con el que pueda
 * confundirse una venta histórica. La columna queda nullable — se endurece a
 * NOT NULL en una fase posterior, una vez verificado en producción que el
 * 100% de las inserciones nuevas la escriben.
 */
final class Version20260801100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade provider a communication_sale_info (snapshot de proveedor) y siembra communications.provider.default/routing.enabled en sys_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS provider VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE communication_sale_info SET provider = 'ETECSA' WHERE provider IS NULL");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_csi_provider_state ON communication_sale_info (provider, state)');

        // El id no tiene DEFAULT (Doctrine usa estrategia SEQUENCE), así que se toma
        // explícitamente de la secuencia (mismo patrón que Version20260720000000).
        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), v.name, v.value, NOW(), NOW(), TRUE, FALSE
            FROM (VALUES
                ('communications.provider.default', 'ETECSA'),
                ('communications.provider.routing.enabled', '0')
            ) AS v(name, value)
            WHERE NOT EXISTS (
                SELECT 1 FROM sys_config sc WHERE sc.property_name = v.name
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name IN ('communications.provider.default', 'communications.provider.routing.enabled')");
        $this->addSql('DROP INDEX IF EXISTS idx_csi_provider_state');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS provider');
    }
}
