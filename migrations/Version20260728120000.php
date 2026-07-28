<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Centro de notificaciones in-app: bandeja con audiencia (usuario, rol,
 * cliente o global), recibos de lectura por usuario, y tickets de un solo uso
 * para autenticar el stream SSE sin abrir sesión PHP (ver
 * DashboardNotificationsController::stream).
 */
final class Version20260728120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification, notification_receipt and notification_stream_ticket tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notification (
                id             SERIAL PRIMARY KEY,
                type           VARCHAR(60)  NOT NULL,
                level          VARCHAR(10)  NOT NULL DEFAULT 'INFO',
                audience       VARCHAR(10)  NOT NULL,
                target_user_id INT          DEFAULT NULL,
                target_role    VARCHAR(50)  DEFAULT NULL,
                client_id      INT          DEFAULT NULL,
                environment_id INT          DEFAULT NULL,
                title          VARCHAR(180) NOT NULL,
                body           TEXT         DEFAULT NULL,
                link           VARCHAR(255) DEFAULT NULL,
                data           JSON         DEFAULT NULL,
                group_key      VARCHAR(160) DEFAULT NULL,
                group_count    INT          NOT NULL DEFAULT 1,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT chk_notification_audience
                    CHECK (audience IN ('USER', 'ROLE', 'CLIENT', 'GLOBAL')),
                CONSTRAINT chk_notification_target CHECK (
                    (audience = 'USER'   AND target_user_id IS NOT NULL) OR
                    (audience = 'ROLE'   AND target_role    IS NOT NULL) OR
                    (audience = 'CLIENT' AND client_id      IS NOT NULL) OR
                    (audience = 'GLOBAL')
                ),
                CONSTRAINT fk_notification_target_user FOREIGN KEY (target_user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
                CONSTRAINT fk_notification_client FOREIGN KEY (client_id)
                    REFERENCES client (id) ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED,
                CONSTRAINT fk_notification_environment FOREIGN KEY (environment_id)
                    REFERENCES environment (id) ON DELETE SET NULL DEFERRABLE INITIALLY DEFERRED
            )
        SQL);

        // Índices parciales por audiencia: el conteo de no leídas solo necesita
        // tocar el subconjunto de filas de la audiencia relevante, nunca la tabla entera.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notif_aud_user ON notification (target_user_id, created_at DESC) WHERE audience = \'USER\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notif_aud_role ON notification (target_role, created_at DESC) WHERE audience = \'ROLE\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notif_aud_client ON notification (client_id, created_at DESC) WHERE audience = \'CLIENT\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notif_aud_global ON notification (created_at DESC) WHERE audience = \'GLOBAL\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notif_expires ON notification (expires_at) WHERE expires_at IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_notif_group_key ON notification (group_key) WHERE group_key IS NOT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notification_receipt (
                id              SERIAL PRIMARY KEY,
                notification_id INT NOT NULL,
                user_id         INT NOT NULL,
                read_at         TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                dismissed_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT uq_notification_receipt UNIQUE (notification_id, user_id),
                CONSTRAINT fk_receipt_notification FOREIGN KEY (notification_id)
                    REFERENCES notification (id) ON DELETE CASCADE,
                CONSTRAINT fk_receipt_user FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_receipt_user_notification ON notification_receipt (user_id, notification_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS notification_stream_ticket (
                id             SERIAL PRIMARY KEY,
                token_hash     VARCHAR(128) NOT NULL,
                user_id        INT NOT NULL,
                environment_id INT DEFAULT NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at        TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT uq_stream_ticket_token_hash UNIQUE (token_hash),
                CONSTRAINT fk_stream_ticket_user FOREIGN KEY (user_id)
                    REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT fk_stream_ticket_environment FOREIGN KEY (environment_id)
                    REFERENCES environment (id) ON DELETE SET NULL
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_stream_ticket_expires ON notification_stream_ticket (expires_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_stream_ticket_used_at ON notification_stream_ticket (used_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS notification_stream_ticket');
        $this->addSql('DROP TABLE IF EXISTS notification_receipt');
        $this->addSql('DROP TABLE IF EXISTS notification');
    }
}
