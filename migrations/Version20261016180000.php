<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-276: la pantalla «Gift cards» (/apps/market-gift-cards) entra en el menú del mercado, dentro de «Operación», con permiso de
 * administrador. Idempotente. Reversa (down): borra el item y sus permisos.
 */
final class Version20261016180000 extends AbstractMigration
{
    private const TITLE = 'menu.apps.market-gift-cards.title';

    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Gift cards" dentro de Mercado > Operación y su permiso ROLE_ADMIN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            SELECT nextval('navigation_item_id_seq'), parent.id, 'menu.apps.market-gift-cards.title', 'basic',
                   'heroicons_outline:gift', '/apps/market-gift-cards', TRUE, NOW(), NOW(), '00183'
            FROM navigation_item parent
            WHERE parent.title = 'menu.market.operation.title'
              AND NOT EXISTS (SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.market-gift-cards.title')
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO user_permission (id, item_id, client_id, user_info_id, is_active, min_role_required, created_at, updated_at)
            SELECT nextval('user_permission_id_seq'), item.id, NULL, NULL, TRUE, 'ROLE_ADMIN', NOW(), NOW()
            FROM navigation_item item
            WHERE item.title = 'menu.apps.market-gift-cards.title'
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = '" . self::TITLE . "')");
        $this->addSql("DELETE FROM navigation_item WHERE title = '" . self::TITLE . "'");
    }
}
