<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ping periódico de proveedores: tabla provider_availability. Guarda solo el
 * estado AUTO (lo que decide el último ping) y la auditoría del último
 * cambio manual — el interruptor MANUAL en sí sigue viviendo en sys_config
 * (provider.{code}.{type}.active), esta tabla no lo escribe ni lo lee.
 *
 * Aditiva y sin efecto hasta que exista una fila: mientras no haya fila para
 * un (provider, environment_type), App\Service\Provider\ProviderAvailabilityService
 * trata auto_enabled como true (no bloquea nada nuevo).
 */
final class Version20260804130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea provider_availability (estado AUTO del ping periódico de proveedores)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS provider_availability_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS provider_availability (
                id                    INT          NOT NULL,
                provider              VARCHAR(20)  NOT NULL,
                environment_type      VARCHAR(10)  NOT NULL,
                auto_enabled          BOOLEAN      NOT NULL DEFAULT TRUE,
                last_action_type      VARCHAR(10)      NULL,
                last_action_by_id     INT              NULL,
                last_action_at        TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                last_action_reason    VARCHAR(255)     NULL,
                last_ping_at          TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                last_ping_success     BOOLEAN          NULL,
                last_ping_latency_ms  INT              NULL,
                last_ping_error       TEXT             NULL,
                last_ping_details     JSON             NULL,
                created_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE provider_availability
                ADD CONSTRAINT FK_PA_USER FOREIGN KEY (last_action_by_id) REFERENCES "user" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_provider_availability
                ON provider_availability (provider, environment_type)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS provider_availability');
        $this->addSql('DROP SEQUENCE IF EXISTS provider_availability_id_seq');
    }
}
