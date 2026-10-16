<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016190000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016190000.php';

/**
 * COMR-276: el lote recuerda el rango con que se emitió. Idempotente.
 *
 * @covers \DoctrineMigrations\Version20261016190000
 */
class Version20261016190000Test extends ProviderFunctionalTestCase
{
    public function testTheBatchHasTheRangeColumns(): void
    {
        $columns = $this->em->getConnection()->fetchFirstColumn(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'market_gift_card_batch' AND column_name IN ('value_min_minor', 'value_max_minor', 'step_minor')"
        );
        sort($columns);

        $this->assertSame(['step_minor', 'value_max_minor', 'value_min_minor'], $columns);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016190000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_gift_card_batch'));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016190000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
