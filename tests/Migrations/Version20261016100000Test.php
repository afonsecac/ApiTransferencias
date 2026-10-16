<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016100000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016100000.php';

/**
 * COMR-286: siembra de provincias y municipios; idempotente y sin pisar el estado activo/inactivo.
 *
 * @covers \DoctrineMigrations\Version20261016100000
 */
class Version20261016100000Test extends ProviderFunctionalTestCase
{
    private function runUp(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016100000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    public function testRunningItAgainKeepsTheCountsAndTheState(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("UPDATE market_municipality SET is_active = FALSE WHERE code = 'boyeros'");

        $this->runUp();

        $this->assertSame(168, (int) $connection->fetchOne('SELECT count(*) FROM market_municipality'));
        $this->assertSame(16, (int) $connection->fetchOne('SELECT count(*) FROM market_province'));
        $this->assertFalse((bool) $connection->fetchOne("SELECT is_active FROM market_municipality WHERE code = 'boyeros'"));
    }

    public function testItRefreshesANameWithoutChangingTheState(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("UPDATE market_municipality SET name = 'x', search_name = 'x' WHERE code = 'playa'");

        $this->runUp();

        $this->assertSame('Playa', $connection->fetchOne("SELECT name FROM market_municipality WHERE code = 'playa'"));
        $this->assertSame('playa', $connection->fetchOne("SELECT search_name FROM market_municipality WHERE code = 'playa'"));
    }

    public function testItAddsTheMenuItemWithAdminPermission(): void
    {
        $roles = $this->em->getConnection()->fetchFirstColumn(
            "SELECT up.min_role_required FROM user_permission up JOIN navigation_item ni ON ni.id = up.item_id WHERE ni.title = 'menu.apps.market-geography.title'"
        );
        $this->assertSame(['ROLE_ADMIN'], $roles);
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016100000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
