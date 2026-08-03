<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 4 del enrutado multi-proveedor: siembra provider.dtone.{test|prod}.base_url
 * (público, documentado, no es secreto) para que DTOneHttpClient tenga un
 * default sin depender de que un admin lo configure primero. api_key/
 * api_secret NO se siembran a propósito: sin ellas, ProviderCredentialsResolver
 * devuelve null y DTOneHttpClient lanza PROVIDER_CREDENTIALS_MISSING en vez
 * de intentar una llamada sin autenticar — deben cargarse vía el dashboard
 * (sys_config, isEncrypted=true) cuando haya credenciales reales.
 */
final class Version20260801160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra provider.dtone.{test|prod}.base_url en sys_config (Fase 4, DTOne real)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), v.name, v.value, NOW(), NOW(), TRUE, FALSE
            FROM (VALUES
                ('provider.dtone.test.base_url', 'https://preprod-dvs-api.dtone.com'),
                ('provider.dtone.prod.base_url', 'https://dvs-api.dtone.com')
            ) AS v(name, value)
            WHERE NOT EXISTS (
                SELECT 1 FROM sys_config sc WHERE sc.property_name = v.name
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name IN ('provider.dtone.test.base_url', 'provider.dtone.prod.base_url')");
    }
}
