<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Menú del mercado como en los mockups del lienzo de diseño: un grupo «Mercado» con las secciones «Catálogo» (Productos,
 * Vendedores y marcas, Categorías) y «Organización» (Unidades organizativas, Perfiles fiscales), en lugar de pantallas
 * sueltas bajo «Configuración del sistema». «Vendedores» y «Marcas» pasan a ser una sola entrada (la pantalla unificada
 * /apps/market-sellers-brands): el item de vendedores se renombra y el de marcas se desactiva (no se borra, para poder
 * revertir). Solo cambia la estructura del menú; los permisos de cada pantalla no se tocan y los nuevos contenedores
 * son ROLE_ADMIN.
 *
 * Idempotente. Reversa (down): devuelve cada pantalla a «Configuración del sistema», reactiva «Marcas», restaura el item
 * de vendedores y borra los contenedores nuevos con sus permisos.
 */
final class Version20261014220000 extends AbstractMigration
{
    private const GROUP = 'menu.market.title';
    private const CATALOG = 'menu.market.catalog.title';
    private const ORGANIZATION = 'menu.market.organization.title';

    public function getDescription(): string
    {
        return 'Agrupa las pantallas del mercado en un menú «Mercado» (Catálogo y Organización) y unifica vendedores y marcas';
    }

    public function up(Schema $schema): void
    {
        $this->addContainer(self::GROUP, 'group', 'heroicons_outline:shopping-bag', '00150', null);
        $this->addContainer(self::CATALOG, 'collapsable', 'heroicons_outline:squares-2x2', '00151', self::GROUP);
        $this->addContainer(self::ORGANIZATION, 'collapsable', 'heroicons_outline:building-office-2', '00152', self::GROUP);

        // Vendedores y marcas: el item de vendedores se renombra y absorbe al de marcas.
        $this->addSql(<<<'SQL'
            UPDATE navigation_item
            SET title = 'menu.apps.market-sellers-brands.title', link = '/apps/market-sellers-brands', updated_at = NOW()
            WHERE title = 'menu.apps.sellers.title'
              AND NOT EXISTS (SELECT 1 FROM navigation_item WHERE title = 'menu.apps.market-sellers-brands.title')
        SQL);
        $this->addSql("UPDATE navigation_item SET is_active = FALSE, updated_at = NOW() WHERE title = 'menu.apps.market-brands.title'");

        $this->move('menu.apps.market-products.title', self::CATALOG, '00161');
        $this->move('menu.apps.market-sellers-brands.title', self::CATALOG, '00162');
        $this->move('menu.apps.market-categories.title', self::CATALOG, '00163');
        $this->move('menu.apps.org-units.title', self::ORGANIZATION, '00171');
        $this->move('menu.apps.fiscal-profiles.title', self::ORGANIZATION, '00172');
    }

    public function down(Schema $schema): void
    {
        $this->move('menu.apps.org-units.title', 'menu.apps.sys-config.title', '00129');
        $this->move('menu.apps.fiscal-profiles.title', 'menu.apps.sys-config.title', '00128');
        $this->move('menu.apps.market-products.title', 'menu.apps.sys-config.title', '00131');
        $this->move('menu.apps.market-categories.title', 'menu.apps.sys-config.title', '00133');
        $this->move('menu.apps.market-sellers-brands.title', 'menu.apps.sys-config.title', '00130');
        $this->addSql(<<<'SQL'
            UPDATE navigation_item
            SET title = 'menu.apps.sellers.title', link = '/apps/sellers', updated_at = NOW()
            WHERE title = 'menu.apps.market-sellers-brands.title'
        SQL);
        $this->addSql("UPDATE navigation_item SET is_active = TRUE, updated_at = NOW() WHERE title = 'menu.apps.market-brands.title'");

        $titles = "'" . self::CATALOG . "', '" . self::ORGANIZATION . "', '" . self::GROUP . "'";
        $this->addSql("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title IN ($titles))");
        // Los contenedores cuelgan unos de otros: primero las secciones, después el grupo.
        $this->addSql("DELETE FROM navigation_item WHERE title IN ('" . self::CATALOG . "', '" . self::ORGANIZATION . "')");
        $this->addSql("DELETE FROM navigation_item WHERE title = '" . self::GROUP . "'");
    }

    private function addContainer(string $title, string $type, string $icon, string $order, ?string $parentTitle): void
    {
        $parentId = $parentTitle === null ? 'NULL' : "(SELECT id FROM navigation_item WHERE title = '$parentTitle')";
        $this->addSql(<<<SQL
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            SELECT nextval('navigation_item_id_seq'), $parentId, '$title', '$type', '$icon', NULL, TRUE, NOW(), NOW(), '$order'
            WHERE NOT EXISTS (SELECT 1 FROM navigation_item WHERE title = '$title')
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
