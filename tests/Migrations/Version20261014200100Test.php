<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014200100;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014200100.php';

/**
 * COMR-70: entrada de menú «Marcas» (/apps/market-brands), con su permiso ROLE_ADMIN. Cuelga del
 * mismo grupo que «Disponibilidad de proveedores» y es idempotente.
 *
 * @covers \DoctrineMigrations\Version20261014200100
 */
class Version20261014200100Test extends ProviderFunctionalTestCase
{
    private const TITLE = 'menu.apps.market-brands.title';

    protected function setUp(): void
    {
        parent::setUp();

        // La base de pruebas solo trae el esquema: se siembra el grupo padre y el item hermano que la migración
        // usa como referencia (en producción los crearon migraciones anteriores). Todo dentro de la transacción del test.
        $connection = $this->em->getConnection();
        $connection->executeStatement(<<<'SQL'
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            VALUES (nextval('navigation_item_id_seq'), NULL, 'menu.apps.sys-config.title', 'collapsable', 'heroicons_outline:cog', NULL, TRUE, NOW(), NOW(), '00120')
        SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            SELECT nextval('navigation_item_id_seq'), parent.id, 'menu.apps.provider-availability.title', 'basic', 'heroicons_outline:signal', '/apps/provider-availability', TRUE, NOW(), NOW(), '00126'
            FROM navigation_item parent WHERE parent.title = 'menu.apps.sys-config.title'
        SQL);
    }

    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014200100($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function items(): array
    {
        return $this->em->getConnection()->fetchAllAssociative('SELECT id, link, parent_id, is_active FROM navigation_item WHERE title = :t', ['t' => self::TITLE]);
    }

    public function testItAddsTheMenuItemUnderTheSameGroupAsProviderAvailability(): void
    {
        $this->runMigration();

        $items = $this->items();
        $this->assertCount(1, $items);
        $this->assertSame('/apps/market-brands', $items[0]['link']);
        $this->assertTrue((bool) $items[0]['is_active']);
        $siblingParent = $this->em->getConnection()->fetchOne("SELECT parent_id FROM navigation_item WHERE title = 'menu.apps.provider-availability.title'");
        $this->assertSame($siblingParent, $items[0]['parent_id']);
    }

    public function testItGrantsAccessToAdministratorsOnly(): void
    {
        $this->runMigration();

        $roles = $this->em->getConnection()->fetchFirstColumn(
            'SELECT up.min_role_required FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = :t',
            ['t' => self::TITLE]
        );
        $this->assertSame(['ROLE_ADMIN'], $roles);
    }

    public function testRunningItTwiceDoesNotDuplicateTheItemNorItsPermission(): void
    {
        $this->runMigration();
        $this->runMigration();

        $this->assertCount(1, $this->items());
        $permissions = $this->em->getConnection()->fetchOne(
            'SELECT count(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = :t',
            ['t' => self::TITLE]
        );
        $this->assertSame(1, (int) $permissions);
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014200100($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
