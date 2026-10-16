<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016120000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016120000.php';

/**
 * COMR-285: Mercado › Operación con «Almacenes»; «Provincias y municipios» se muda allí. Idempotente y reversible.
 *
 * @covers \DoctrineMigrations\Version20261016120000
 */
class Version20261016120000Test extends ProviderFunctionalTestCase
{
    private function migrate(string $direction = 'up'): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016120000($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function parentOf(string $title): ?string
    {
        $parent = $this->em->getConnection()->fetchOne(
            'SELECT p.title FROM navigation_item ni LEFT JOIN navigation_item p ON p.id = ni.parent_id WHERE ni.title = :t',
            ['t' => $title]
        );

        return $parent === false ? null : $parent;
    }

    public function testWarehousesAndGeographyLiveUnderOperation(): void
    {
        $this->assertSame('menu.market.title', $this->parentOf('menu.market.operation.title'));
        $this->assertSame('menu.market.operation.title', $this->parentOf('menu.apps.market-warehouses.title'));
        $this->assertSame('menu.market.operation.title', $this->parentOf('menu.apps.market-geography.title'));
        $this->assertSame('/apps/market-warehouses', $this->em->getConnection()->fetchOne("SELECT link FROM navigation_item WHERE title = 'menu.apps.market-warehouses.title'"));
    }

    public function testBothNewItemsAreForAdministratorsOnly(): void
    {
        foreach (['menu.market.operation.title', 'menu.apps.market-warehouses.title'] as $title) {
            $roles = $this->em->getConnection()->fetchFirstColumn(
                'SELECT up.min_role_required FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = :t',
                ['t' => $title]
            );
            $this->assertSame(['ROLE_ADMIN'], $roles, $title);
        }
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $this->migrate();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title IN ('menu.market.operation.title', 'menu.apps.market-warehouses.title') GROUP BY title LIMIT 1"));
        $this->assertSame(2, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title IN ('menu.market.operation.title', 'menu.apps.market-warehouses.title')"));
    }

    public function testDownReturnsGeographyToOrganizationAndRemovesTheRest(): void
    {
        // La pantalla de stock (COMR-193) cuelga de «Operación»: sus migraciones posteriores se revierten antes.
        $this->em->getConnection()->executeStatement("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.market-stock.title')");
        $this->em->getConnection()->executeStatement("DELETE FROM navigation_item WHERE title = 'menu.apps.market-stock.title'");
        $this->migrate('down');

        $this->assertSame('menu.market.organization.title', $this->parentOf('menu.apps.market-geography.title'));
        $this->assertNull($this->em->getConnection()->fetchOne("SELECT id FROM navigation_item WHERE title = 'menu.apps.market-warehouses.title'") ?: null);
        $this->assertNull($this->em->getConnection()->fetchOne("SELECT id FROM navigation_item WHERE title = 'menu.market.operation.title'") ?: null);
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016120000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
