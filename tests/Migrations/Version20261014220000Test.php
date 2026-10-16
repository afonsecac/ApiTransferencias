<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014220000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014220000.php';

/**
 * Menú del mercado como en los mockups: un grupo «Mercado» con «Catálogo» (Productos, Vendedores y marcas, Categorías) y
 * «Organización» (Unidades organizativas, Perfiles fiscales), en lugar de pantallas sueltas bajo «Configuración del
 * sistema». «Vendedores» y «Marcas» pasan a ser una sola entrada. Idempotente y reversible.
 *
 * @covers \DoctrineMigrations\Version20261014220000
 */
class Version20261014220000Test extends ProviderFunctionalTestCase
{
    private const ITEMS = [
        'menu.apps.org-units.title' => ['/apps/org-units', '00129'],
        'menu.apps.fiscal-profiles.title' => ['/apps/fiscal-profiles', '00128'],
        'menu.apps.sellers.title' => ['/apps/sellers', '00130'],
        'menu.apps.market-products.title' => ['/apps/market-products', '00131'],
        'menu.apps.market-brands.title' => ['/apps/market-brands', '00132'],
        'menu.apps.market-categories.title' => ['/apps/market-categories', '00133'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // La base de pruebas solo trae el esquema: se siembra el estado anterior (pantallas sueltas bajo «Configuración
        // del sistema», cada una con su permiso). Todo dentro de la transacción del test.
        $connection = $this->em->getConnection();
        // Las pantallas que otras migraciones posteriores cuelgan de estos contenedores (COMR-275) no son parte de este estado
        // anterior, y le impedirían a la reversa borrarlos.
        $connection->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.market-buyer-catalog.title')");
        $connection->executeStatement("DELETE FROM navigation_item WHERE title = 'menu.apps.market-buyer-catalog.title'");
        $connection->executeStatement(<<<'SQL'
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            VALUES (nextval('navigation_item_id_seq'), NULL, 'menu.apps.sys-config.title', 'collapsable', 'heroicons_outline:cog', NULL, TRUE, NOW(), NOW(), '00120')
        SQL);
        foreach (self::ITEMS as $title => [$link, $order]) {
            $connection->executeStatement(<<<'SQL'
                INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
                SELECT nextval('navigation_item_id_seq'), parent.id, :title, 'basic', 'heroicons_outline:tag', :link, TRUE, NOW(), NOW(), :ord
                FROM navigation_item parent WHERE parent.title = 'menu.apps.sys-config.title'
            SQL, ['title' => $title, 'link' => $link, 'ord' => $order]);
        }
    }

    private function runMigration(string $direction = 'up'): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014220000($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    /** @return array<string, mixed>|null */
    private function item(string $title): ?array
    {
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT ni.id, ni.type, ni.link, ni.is_active, ni.order_value, ni.title, p.title AS parent_title
             FROM navigation_item ni LEFT JOIN navigation_item p ON p.id = ni.parent_id WHERE ni.title = :t',
            ['t' => $title]
        );

        return $row === false ? null : $row;
    }

    public function testItCreatesTheMarketGroupWithItsTwoSections(): void
    {
        $this->runMigration();

        $group = $this->item('menu.market.title');
        $this->assertSame('group', $group['type']);
        $this->assertNull($group['parent_title']);
        $this->assertSame('collapsable', $this->item('menu.market.catalog.title')['type']);
        $this->assertSame('menu.market.title', $this->item('menu.market.catalog.title')['parent_title']);
        $this->assertSame('collapsable', $this->item('menu.market.organization.title')['type']);
        $this->assertSame('menu.market.title', $this->item('menu.market.organization.title')['parent_title']);
    }

    public function testItMovesTheScreensIntoTheirSection(): void
    {
        $this->runMigration();

        $this->assertSame('menu.market.catalog.title', $this->item('menu.apps.market-products.title')['parent_title']);
        $this->assertSame('menu.market.catalog.title', $this->item('menu.apps.market-categories.title')['parent_title']);
        $this->assertSame('menu.market.organization.title', $this->item('menu.apps.org-units.title')['parent_title']);
        $this->assertSame('menu.market.organization.title', $this->item('menu.apps.fiscal-profiles.title')['parent_title']);
    }

    public function testSellersAndBrandsBecomeASingleEntry(): void
    {
        $this->runMigration();

        $merged = $this->item('menu.apps.market-sellers-brands.title');
        $this->assertSame('/apps/market-sellers-brands', $merged['link']);
        $this->assertSame('menu.market.catalog.title', $merged['parent_title']);
        $this->assertTrue((bool) $merged['is_active']);
        $this->assertNull($this->item('menu.apps.sellers.title'), 'el item de vendedores se renombra, no se duplica');
        $this->assertFalse((bool) $this->item('menu.apps.market-brands.title')['is_active']);
    }

    public function testTheCatalogSectionKeepsTheMockupOrder(): void
    {
        $this->runMigration();

        $orders = [];
        foreach (['menu.apps.market-products.title', 'menu.apps.market-sellers-brands.title', 'menu.apps.market-categories.title'] as $title) {
            $orders[] = $this->item($title)['order_value'];
        }
        $sorted = $orders;
        sort($sorted);
        $this->assertSame($sorted, $orders);
    }

    public function testItGrantsAccessToAdministratorsOnlyOnTheNewItems(): void
    {
        $this->runMigration();

        foreach (['menu.market.title', 'menu.market.catalog.title', 'menu.market.organization.title'] as $title) {
            $roles = $this->em->getConnection()->fetchFirstColumn(
                'SELECT up.min_role_required FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = :t',
                ['t' => $title]
            );
            $this->assertSame(['ROLE_ADMIN'], $roles, $title);
        }
    }

    public function testRunningItTwiceChangesNothingTheSecondTime(): void
    {
        $this->runMigration();
        $this->runMigration();

        $count = $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title IN ('menu.market.title', 'menu.market.catalog.title', 'menu.market.organization.title')");
        $this->assertSame(3, (int) $count);
        $permissions = $this->em->getConnection()->fetchOne(
            "SELECT count(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title IN ('menu.market.title', 'menu.market.catalog.title', 'menu.market.organization.title')"
        );
        $this->assertSame(3, (int) $permissions);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title = 'menu.apps.market-sellers-brands.title'"));
    }

    public function testDownPutsTheScreensBackWhereTheyWere(): void
    {
        $this->runMigration();
        // Las migraciones posteriores se revierten antes: la pantalla de medidas (COMR-281) cuelga de «Catálogo».
        $this->em->getConnection()->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.market-measure-settings.title')");
        $this->em->getConnection()->executeStatement("DELETE FROM navigation_item WHERE title = 'menu.apps.market-measure-settings.title'");
        // Y las de COMR-286 y COMR-285 (provincias y municipios, almacenes y la sección Operación) cuelgan del grupo.
        foreach (['menu.apps.market-geography.title', 'menu.apps.market-warehouses.title', 'menu.apps.market-stock.title', 'menu.apps.market-gift-cards.title', 'menu.market.operation.title'] as $title) {
            $this->em->getConnection()->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = '$title')");
            $this->em->getConnection()->executeStatement("DELETE FROM navigation_item WHERE title = '$title'");
        }
        $this->runMigration('down');

        $this->assertNull($this->item('menu.market.title'));
        $this->assertNull($this->item('menu.market.catalog.title'));
        foreach (self::ITEMS as $title => [$link, $order]) {
            $item = $this->item($title);
            $this->assertNotNull($item, $title);
            $this->assertSame('menu.apps.sys-config.title', $item['parent_title'], $title);
            $this->assertSame($link, $item['link'], $title);
            $this->assertSame($order, $item['order_value'], $title);
            $this->assertTrue((bool) $item['is_active'], $title);
        }
        $this->assertNull($this->item('menu.apps.market-sellers-brands.title'));
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014220000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
