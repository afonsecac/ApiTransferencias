<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Siembra el item de menú "Disponibilidad de proveedores" — pantalla nueva
 * (dashboard-cm) que muestra la matriz de disponibilidad por proveedor x
 * entorno (ping periódico, interruptor manual auditado) y permite forzar un
 * ping o cambiar el interruptor manual. Mismo grupo padre y mismo criterio
 * de localización por `title` que Version20260801210000 (provider-settings).
 */
final class Version20260804140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Disponibilidad de proveedores" (menu.apps.provider-availability.title) y su permiso ROLE_ADMIN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.provider-availability.title',
                'basic',
                'heroicons_outline:signal',
                '/apps/provider-availability',
                TRUE,
                NOW(),
                NOW(),
                '00126'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.sys-config.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.provider-availability.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO user_permission (id, item_id, client_id, user_info_id, is_active, min_role_required, created_at, updated_at)
            SELECT
                nextval('user_permission_id_seq'),
                item.id,
                NULL,
                NULL,
                TRUE,
                'ROLE_ADMIN',
                NOW(),
                NOW()
            FROM navigation_item item
            WHERE item.title = 'menu.apps.provider-availability.title'
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM user_permission
            WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.provider-availability.title')
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.provider-availability.title'");
    }
}
