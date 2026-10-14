<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014180000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014180000.php';

/**
 * COMR-276 sobre COMR-273: las gift cards son productos digitales. La base obliga a que cada producto sea una cosa o la otra
 * (físico con contenido y entrega, o gift card con categoría, moneda y valor y nada de lo físico), y los productos que ya
 * existían siguen siendo válidos.
 *
 * @covers \DoctrineMigrations\Version20261014180000
 */
class Version20261014180000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014180000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function sellerId(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
    }

    private function insertPhysical(string $sku): void
    {
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at)
             VALUES (:sku, :s, 'draft', '{}', 500, 500, 'g', 'weight', 'g', NOW(), NOW())",
            ['sku' => $sku, 's' => $this->sellerId()],
        );
    }

    /**
     * @param array<string, mixed> $extra columnas adicionales (para provocar un caso inválido)
     */
    private function insertGiftCard(string $sku, string $min = '2500', ?string $max = null, array $extra = []): void
    {
        $columns = ['sku' => $sku, 'seller_id' => $this->sellerId(), 'status' => 'draft', 'texts' => '{}', 'gift_card_category' => 'retail', 'gift_card_currency' => 'USD', 'gift_card_min_minor' => $min, 'gift_card_max_minor' => $max] + $extra;
        $names = implode(', ', array_keys($columns));
        $params = implode(', ', array_map(static fn (string $c) => ':' . $c, array_keys($columns)));
        $this->em->getConnection()->executeStatement("INSERT INTO market_product ($names, created_at, updated_at) VALUES ($params, NOW(), NOW())", $columns);
    }

    public function testRunningItAgainKeepsTheExistingProductsValid(): void
    {
        $this->insertPhysical('FISICO-1');

        $this->runMigration();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_product WHERE sku = 'FISICO-1'"));
    }

    public function testItAcceptsAFixedAndARangedGiftCard(): void
    {
        $this->insertGiftCard('GC-FIJA');
        $this->insertGiftCard('GC-RANGO', '500', '10000');

        $this->assertSame(2, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_product WHERE sku LIKE 'GC-%'"));
    }

    public function testAGiftCardCannotCarryPhysicalData(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_type');

        $this->insertGiftCard('GC-MAL', '2500', null, ['content_base' => 500, 'content_original' => 500, 'content_unit' => 'g']);
    }

    public function testTheMaximumMustBeGreaterThanTheMinimum(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_type');

        $this->insertGiftCard('GC-RANGO-MAL', '500', '500');
    }

    public function testTheMinimumMustBePositive(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_type');

        $this->insertGiftCard('GC-CERO', '0');
    }

    public function testAPhysicalProductCannotCarryGiftCardData(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_type');

        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, gift_card_currency, created_at, updated_at)
             VALUES ('FISICO-MAL', :s, 'draft', '{}', 500, 500, 'g', 'weight', 'g', 'USD', NOW(), NOW())",
            ['s' => $this->sellerId()],
        );
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014180000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
