<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Siembra el menú del catálogo agnóstico de proveedor (V2 Fase 5, frontend):
 * un padre "Catálogo" (colapsable) con dos hijos, "Paquetes" y "Contratos"
 * — pantallas nuevas en dashboard-cm bajo /apps/catalog/packages y
 * /apps/catalog/contracts, que consumen los endpoints /dashboard/api/catalog/packages
 * y /dashboard/api/catalog/contracts (V2 Fase 3, ya en producción). Mismo
 * patrón que Version20260809120300 (menú de "Contratos de precio" V1) —
 * top-level bajo menu.apps.title (id 4), no anidado bajo "Clientes": el
 * catálogo V2 es global, no propiedad de un cliente.
 */
final class Version20260810140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el menú "Catálogo" (paquetes/contratos V2) y sus permisos ROLE_ADMIN';
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
                'menu.apps.catalog.title',
                'collapsable',
                'heroicons_outline:squares-2x2',
                '/apps/catalog',
                TRUE,
                NOW(),
                NOW(),
                '00136'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.catalog.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.catalog.packages.title',
                'basic',
                'heroicons_outline:archive-box',
                '/apps/catalog/packages',
                TRUE,
                NOW(),
                NOW(),
                '00137'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.catalog.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.catalog.packages.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.catalog.contracts.title',
                'basic',
                'heroicons_outline:document-currency-dollar',
                '/apps/catalog/contracts',
                TRUE,
                NOW(),
                NOW(),
                '00138'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.catalog.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.catalog.contracts.title'
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
            WHERE item.title IN ('menu.apps.catalog.title', 'menu.apps.catalog.packages.title', 'menu.apps.catalog.contracts.title')
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
            WHERE item_id IN (
                SELECT id FROM navigation_item
                WHERE title IN ('menu.apps.catalog.title', 'menu.apps.catalog.packages.title', 'menu.apps.catalog.contracts.title')
            )
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title IN ('menu.apps.catalog.packages.title', 'menu.apps.catalog.contracts.title')");
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.catalog.title'");
    }
}
