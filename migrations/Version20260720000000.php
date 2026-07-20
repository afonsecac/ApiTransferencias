<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Endurecimiento del 2FA y siembra de su configuración global.
 */
final class Version20260720000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade two_factor_last_time_step (anti-replay TOTP) y siembra las claves 2fa.* en sys_config';
    }

    public function up(Schema $schema): void
    {
        // Último time step TOTP consumido: impide reutilizar un código dentro de su
        // ventana de validez. Nullable porque los usuarios ya enrolados aún no tienen uno.
        $this->addSql('ALTER TABLE "user" ADD COLUMN IF NOT EXISTS two_factor_last_time_step BIGINT DEFAULT NULL');

        // Hasta ahora estas claves solo nacían al guardar desde el panel de administración:
        // en un entorno nuevo la política caía a los defaults del código sin dejar rastro
        // en la base de datos. Se siembran explícitamente con los valores por defecto.
        // El id no tiene DEFAULT (Doctrine usa estrategia SEQUENCE), así que se toma
        // explícitamente de la secuencia.
        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), v.name, v.value, NOW(), NOW(), TRUE, FALSE
            FROM (VALUES
                ('2fa.mode',   'optional'),
                ('2fa.method', 'totp')
            ) AS v(name, value)
            WHERE NOT EXISTS (
                SELECT 1 FROM sys_config sc WHERE sc.property_name = v.name
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN IF EXISTS two_factor_last_time_step');
        // 2fa.deadline no se siembra, así que no se elimina: podría existir por
        // configuración real del administrador.
        $this->addSql("DELETE FROM sys_config WHERE property_name IN ('2fa.mode', '2fa.method')");
    }
}
