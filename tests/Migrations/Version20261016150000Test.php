<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016150000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016150000.php';

/**
 * COMR-193: la pantalla «Stock» entra en Mercado › Operación, con permiso de administrador. Idempotente y reversible.
 *
 * @covers \DoctrineMigrations\Version20261016150000
 */
class Version20261016150000Test extends ProviderFunctionalTestCase
{
    private function migrate(string $direction = 'up'): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016150000($connection, new NullLogger());
        $migration->$direction(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    public function testTheStockScreenHangsFromOperationForAdministrators(): void
    {
        $row = $this->em->getConnection()->fetchAssociative(
            "SELECT p.title AS parent, ni.link FROM navigation_item ni JOIN navigation_item p ON p.id = ni.parent_id WHERE ni.title = 'menu.apps.market-stock.title'"
        );
        $this->assertSame('menu.market.operation.title', $row['parent']);
        $this->assertSame('/apps/market-stock', $row['link']);
        $roles = $this->em->getConnection()->fetchFirstColumn(
            "SELECT up.min_role_required FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = 'menu.apps.market-stock.title'"
        );
        $this->assertSame(['ROLE_ADMIN'], $roles);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $this->migrate();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title = 'menu.apps.market-stock.title'"));
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = 'menu.apps.market-stock.title'"));
    }

    public function testDownRemovesTheItem(): void
    {
        $this->migrate('down');

        $this->assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM navigation_item WHERE title = 'menu.apps.market-stock.title'"));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016150000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
