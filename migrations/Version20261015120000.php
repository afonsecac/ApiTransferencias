<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-275: siembra el item de navegación «Vista previa del comprador» (menu.apps.market-buyer-catalog.title) dentro de
 * Mercado › Catálogo, con su permiso ROLE_ADMIN. Aditiva e idempotente: sin el contenedor de Catálogo no hace nada.
 *
 * Reversa (down): borra el permiso y el item.
 */
final class Version20261015120000 extends AbstractMigration
{
    private const TITLE = 'menu.apps.market-buyer-catalog.title';

    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Vista previa del comprador" (menu.apps.market-buyer-catalog.title) y su permiso ROLE_ADMIN';
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
                'menu.apps.market-buyer-catalog.title',
                'basic',
                'heroicons_outline:eye',
                '/apps/market-buyer-catalog',
                TRUE,
                NOW(),
                NOW(),
                '00164'
            FROM navigation_item parent
            WHERE parent.title = 'menu.market.catalog.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.market-buyer-catalog.title'
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
            WHERE item.title = 'menu.apps.market-buyer-catalog.title'
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
            WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.market-buyer-catalog.title')
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.market-buyer-catalog.title'");
    }
}
