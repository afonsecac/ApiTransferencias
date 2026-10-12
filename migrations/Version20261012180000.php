<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Siembra el item de menú "Ventas por revisar" (COMR-258) — pantalla nueva de dashboard-cm que lista las ventas
 * inciertas (COMR-257) y permite consultarlas de nuevo o resolverlas a mano con motivo. Mismo grupo padre y mismo
 * criterio de localización por `title` que Version20260804140000 (disponibilidad de proveedores).
 */
final class Version20261012180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Ventas por revisar" (menu.apps.uncertain-sales.title) y su permiso ROLE_ADMIN';
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
                'menu.apps.uncertain-sales.title',
                'basic',
                'heroicons_outline:exclamation-triangle',
                '/apps/uncertain-sales',
                TRUE,
                NOW(),
                NOW(),
                '00127'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.sys-config.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.uncertain-sales.title'
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
            WHERE item.title = 'menu.apps.uncertain-sales.title'
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
            WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.uncertain-sales.title')
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.uncertain-sales.title'");
    }
}
