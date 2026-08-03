<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Siembra el item de menú "Enrutado de proveedores" bajo el grupo existente
 * "menu.apps.sys-config.title" (mismo grupo que aloja las variables de
 * sys-config y la configuración de comunicaciones — encaja porque es otra
 * pantalla de configuración de backend, no de negocio del día a día).
 *
 * El padre se localiza por su `title` (clave de traducción única), no por
 * ID: los navigation_item existentes se sembraron fuera de las migraciones
 * versionadas de este repo (fixture/dump), así que sus IDs numéricos no son
 * un dato estable entre entornos.
 */
final class Version20260801130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Enrutado de proveedores" (menu.apps.provider-routing.title) y su permiso ROLE_ADMIN';
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
                'menu.apps.provider-routing.title',
                'basic',
                'heroicons_outline:arrows-right-left',
                '/apps/provider-routing',
                TRUE,
                NOW(),
                NOW(),
                '00123'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.sys-config.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.provider-routing.title'
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
            WHERE item.title = 'menu.apps.provider-routing.title'
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
            WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.provider-routing.title')
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.provider-routing.title'");
    }
}
