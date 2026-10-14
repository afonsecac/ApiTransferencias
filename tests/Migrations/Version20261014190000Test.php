<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014190000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014190000.php';

/**
 * El tipo de un producto lo dan el servicio, el subservicio y el área, no un campo aparte. La base obliga a que cada producto
 * lleve contenido o valor, y mantiene la clasificación en el vocabulario controlado: el área solo existe bajo su subservicio.
 *
 * @covers \DoctrineMigrations\Version20261014190000
 */
class Version20261014190000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014190000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function sellerId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function insert(string $sku, array $columns): void
    {
        $columns = $columns + ['sku' => $sku, 'seller_id' => $this->sellerId(), 'status' => 'draft', 'service' => 'Gift Cards', 'subservice' => 'Retail', 'texts' => '{}'];
        $names = implode(', ', array_keys($columns));
        $params = implode(', ', array_map(static fn (string $c) => ':' . $c, array_keys($columns)));
        $this->em->getConnection()->executeStatement("INSERT INTO market_product ($names, created_at, updated_at) VALUES ($params, NOW(), NOW())", $columns);
    }

    /**
     * @return array<string, mixed>
     */
    private function physical(): array
    {
        return ['content_base' => 500, 'content_original' => 500, 'content_unit' => 'g', 'delivery_kind' => 'weight', 'delivery_unit' => 'g'];
    }

    /**
     * @return array<string, mixed>
     */
    private function withValue(string $min = '2500', ?string $max = null): array
    {
        return ['value_currency' => 'USD', 'value_min_minor' => $min, 'value_max_minor' => $max];
    }

    public function testRunningItAgainKeepsTheExistingProducts(): void
    {
        $this->insert('FISICO-1', $this->physical());

        $this->runMigration();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_product WHERE sku = 'FISICO-1'"));
    }

    public function testItAcceptsAProductWithContentAndAProductWithFixedOrRangedValue(): void
    {
        $this->insert('FISICO', $this->physical());
        $this->insert('VALOR-FIJO', $this->withValue());
        $this->insert('VALOR-RANGO', $this->withValue('500', '10000'));

        $this->assertSame(3, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_product WHERE sku IN ('FISICO', 'VALOR-FIJO', 'VALOR-RANGO')"));
    }

    public function testItAcceptsTheSupermarketAreaUnderRetail(): void
    {
        $this->insert('SUPER', $this->physical() + ['area' => 'Supermarket']);

        $this->assertSame('Supermarket', $this->em->getConnection()->fetchOne("SELECT area FROM market_product WHERE sku = 'SUPER'"));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badClassifications(): iterable
    {
        yield 'área bajo otro subservicio' => [['subservice' => 'Gaming', 'area' => 'Supermarket']];
        yield 'Retail como área' => [['area' => 'Retail']];
        yield 'área en minúsculas' => [['area' => 'supermarket']];
        yield 'servicio fuera del vocabulario' => [['service' => 'Mobile']];
        yield 'Retail como servicio' => [['service' => 'Retail']];
        yield 'subservicio fuera del vocabulario' => [['subservice' => 'Electronics']];
    }

    /**
     * @param array<string, mixed> $extra
     * @dataProvider badClassifications
     */
    public function testTheClassificationMustStayInTheControlledVocabulary(array $extra): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_classification');

        $this->insert('MAL-CLASIF', $this->physical() + $extra);
    }

    public function testAProductWithValueCannotCarryPhysicalData(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->insert('VALOR-MAL', $this->withValue() + ['content_base' => 500, 'content_original' => 500, 'content_unit' => 'g']);
    }

    public function testTheMaximumMustBeGreaterThanTheMinimum(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->insert('RANGO-MAL', $this->withValue('500', '500'));
    }

    public function testTheMinimumMustBePositive(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->insert('CERO', $this->withValue('0'));
    }

    public function testAProductWithContentCannotCarryAValue(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->insert('FISICO-MAL', $this->physical() + ['value_currency' => 'USD']);
    }

    public function testAProductWithNeitherContentNorValueIsRejected(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_shape');

        $this->insert('VACIO', []);
    }

    public function testThereIsNoTypeColumnAnymore(): void
    {
        $columns = $this->em->getConnection()->fetchFirstColumn("SELECT column_name FROM information_schema.columns WHERE table_name = 'market_product'");

        $this->assertNotContains('type', $columns);
        $this->assertNotContains('gift_card_category', $columns);
        $this->assertContains('subservice', $columns);
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014190000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
