<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261015220000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261015220000.php';

/**
 * COMR-281: siembra las unidades base (kg y l) y el divisor del peso volumétrico (6000) en la configuración del sistema,
 * sin pisar un valor que ya exista.
 *
 * @covers \DoctrineMigrations\Version20261015220000
 */
class Version20261015220000Test extends ProviderFunctionalTestCase
{
    private const KEYS = ['market.measure.weight_base', 'market.measure.volume_base', 'market.measure.volumetric_divisor'];

    private function migrate(string $direction): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261015220000($connection, new NullLogger());
        $direction === 'up' ? $migration->up(new Schema()) : $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function values(): array
    {
        $rows = $this->em->getConnection()->fetchAllKeyValue(
            'SELECT property_name, property_value FROM sys_config WHERE property_name IN (?, ?, ?)',
            self::KEYS,
        );
        ksort($rows);

        return $rows;
    }

    private function clear(): void
    {
        $this->em->getConnection()->executeStatement("DELETE FROM sys_config WHERE property_name LIKE 'market.measure.%'");
    }

    public function testItSeedsTheBaseUnitsAndTheDivisor(): void
    {
        $this->clear();

        $this->migrate('up');

        $this->assertSame(
            ['market.measure.volume_base' => 'l', 'market.measure.volumetric_divisor' => '6000', 'market.measure.weight_base' => 'kg'],
            $this->values(),
        );
    }

    public function testRunningItAgainKeepsAnExistingValue(): void
    {
        $this->clear();
        $this->migrate('up');
        $this->em->getConnection()->executeStatement("UPDATE sys_config SET property_value = '5000' WHERE property_name = 'market.measure.volumetric_divisor'");

        $this->migrate('up');

        $this->assertSame('5000', $this->values()['market.measure.volumetric_divisor']);
        $this->assertCount(3, $this->values());
    }

    public function testDownRemovesTheThreeKeys(): void
    {
        $this->clear();
        $this->migrate('up');

        $this->migrate('down');

        $this->assertSame([], $this->values());
    }
}
