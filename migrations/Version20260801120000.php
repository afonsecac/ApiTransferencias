<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 2 del enrutado multi-proveedor: tabla client_provider_routing.
 * Control de ADMISIÓN (qué proveedor puede consumir un cliente / cuál es su
 * default), no de despacho — el despacho de una venta ya creada lee el
 * snapshot de communication_sale_info.provider (Fase 1), nunca esta tabla.
 *
 * Aditiva y sin efecto hasta que se creen filas: mientras la tabla esté
 * vacía, App\Provider\ProviderResolver sigue devolviendo el proveedor por
 * defecto de sys_config para todo el mundo.
 */
final class Version20260801120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea client_provider_routing (enrutado de proveedor por cliente, Fase 2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS client_provider_routing_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS client_provider_routing (
                id                INT          NOT NULL,
                client_id         INT          NOT NULL,
                environment_id    INT              NULL,
                sale_type         VARCHAR(10)      NULL,
                provider          VARCHAR(20)  NOT NULL,
                fallback_provider VARCHAR(20)      NULL,
                is_active         BOOLEAN      NOT NULL DEFAULT TRUE,
                notes             VARCHAR(255)     NULL,
                created_by_id     INT              NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE client_provider_routing
                ADD CONSTRAINT FK_CPR_CLIENT FOREIGN KEY (client_id) REFERENCES client (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_provider_routing
                ADD CONSTRAINT FK_CPR_ENVIRONMENT FOREIGN KEY (environment_id) REFERENCES environment (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE client_provider_routing
                ADD CONSTRAINT FK_CPR_USER FOREIGN KEY (created_by_id) REFERENCES "user" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        // PostgreSQL 18 (serverVersion=18): NULLS NOT DISTINCT trata NULL como valor
        // comparable, así (client, NULL, NULL) solo puede existir una vez activa.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_cpr_scope
                ON client_provider_routing (client_id, environment_id, sale_type)
                NULLS NOT DISTINCT
                WHERE is_active = TRUE
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_cpr_client_active ON client_provider_routing (client_id) WHERE is_active = TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS client_provider_routing');
        $this->addSql('DROP SEQUENCE IF EXISTS client_provider_routing_id_seq');
    }
}
