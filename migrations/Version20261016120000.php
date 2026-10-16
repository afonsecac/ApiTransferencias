<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-285: la sección «Operación» del menú del mercado (Mercado › Operación) con la pantalla «Almacenes», y «Provincias y
 * municipios» (COMR-286) pasa de «Organización» a «Operación», como decidió el diseño. Permiso de administrador. Idempotente.
 * Reversa (down): devuelve «Provincias y municipios» a «Organización» y borra «Almacenes» y la sección.
 */
final class Version20261016120000 extends AbstractMigration
{
    private const OPERATION = 'menu.market.operation.title';
    private const WAREHOUSES = 'menu.apps.market-warehouses.title';
    private const GEOGRAPHY = 'menu.apps.market-geography.title';

    public function getDescription(): string
    {
        return 'Añade Mercado > Operación con la pantalla "Almacenes" y mueve "Provincias y municipios" a esa sección';
    }

    public function up(Schema $schema): void
    {
        $this->container(self::OPERATION, 'collapsable', 'heroicons_outline:truck', '00153', 'menu.market.title');
        $this->container(self::WAREHOUSES, 'basic', 'heroicons_outline:building-storefront', '00180', self::OPERATION, '/apps/market-warehouses');
        $this->move(self::GEOGRAPHY, self::OPERATION, '00181');
    }

    public function down(Schema $schema): void
    {
        $this->move(self::GEOGRAPHY, 'menu.market.organization.title', '00170');
        $titles = "'" . self::WAREHOUSES . "', '" . self::OPERATION . "'";
        $this->addSql("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title IN ($titles))");
        $this->addSql("DELETE FROM navigation_item WHERE title = '" . self::WAREHOUSES . "'");
        $this->addSql("DELETE FROM navigation_item WHERE title = '" . self::OPERATION . "'");
    }

    private function container(string $title, string $type, string $icon, string $order, string $parentTitle, ?string $link = null): void
    {
        $linkSql = $link === null ? 'NULL' : "'$link'";
        $this->addSql(<<<SQL
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            SELECT nextval('navigation_item_id_seq'), parent.id, '$title', '$type', '$icon', $linkSql, TRUE, NOW(), NOW(), '$order'
            FROM navigation_item parent
            WHERE parent.title = '$parentTitle'
              AND NOT EXISTS (SELECT 1 FROM navigation_item WHERE title = '$title')
        SQL);
        $this->addSql(<<<SQL
            INSERT INTO user_permission (id, item_id, client_id, user_info_id, is_active, min_role_required, created_at, updated_at)
            SELECT nextval('user_permission_id_seq'), item.id, NULL, NULL, TRUE, 'ROLE_ADMIN', NOW(), NOW()
            FROM navigation_item item
            WHERE item.title = '$title'
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }

    private function move(string $title, string $parentTitle, string $order): void
    {
        $this->addSql(<<<SQL
            UPDATE navigation_item
            SET parent_id = (SELECT id FROM navigation_item WHERE title = '$parentTitle'), order_value = '$order', updated_at = NOW()
            WHERE title = '$title'
              AND EXISTS (SELECT 1 FROM navigation_item WHERE title = '$parentTitle')
        SQL);
    }
}
