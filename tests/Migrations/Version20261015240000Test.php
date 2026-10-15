<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261015240000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261015240000.php';

/**
 * COMR-281: el producto recuerda en qué unidad se escribieron sus dimensiones (mm por defecto). Los productos que ya existían
 * conservan sus milímetros y quedan con «mm».
 *
 * @covers \DoctrineMigrations\Version20261015240000
 */
class Version20261015240000Test extends ProviderFunctionalTestCase
{
    private function migrate(string $direction): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261015240000($connection, new NullLogger());
        $direction === 'up' ? $migration->up(new Schema()) : $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function hasColumn(): bool
    {
        return (bool) $this->em->getConnection()->fetchOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'market_product' AND column_name = 'dimensions_unit'");
    }

    private function insertProduct(string $sku): void
    {
        $sellerId = (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, service, subservice, texts, content_base, content_original, content_unit, length_mm, width_mm, height_mm, delivery_kind, delivery_unit, created_at, updated_at)
             VALUES (?, ?, 'draft', 'Gift Cards', 'Retail', '{}', 1, 1, 'kg', 100, 50, 20, 'weight', 'kg', NOW(), NOW())",
            [$sku, $sellerId],
        );
    }

    public function testExistingProductsKeepTheirMillimetersAndGetMillimetersAsUnit(): void
    {
        $this->em->getConnection()->executeStatement('ALTER TABLE market_product DROP COLUMN IF EXISTS dimensions_unit');
        $this->insertProduct('D-VIEJO');

        $this->migrate('up');

        $row = $this->em->getConnection()->fetchAssociative("SELECT dimensions_unit, length_mm FROM market_product WHERE sku = 'D-VIEJO'");
        $this->assertSame('mm', $row['dimensions_unit']);
        $this->assertEquals(100, $row['length_mm']);
    }

    public function testRunningItAgainKeepsTheChosenUnit(): void
    {
        $this->migrate('up');
        $this->insertProduct('D-NUEVO');
        $this->em->getConnection()->executeStatement("UPDATE market_product SET dimensions_unit = 'in' WHERE sku = 'D-NUEVO'");

        $this->migrate('up');

        $this->assertSame('in', $this->em->getConnection()->fetchOne("SELECT dimensions_unit FROM market_product WHERE sku = 'D-NUEVO'"));
    }

    public function testTheColumnOnlyAcceptsTheKnownUnits(): void
    {
        $this->migrate('up');
        $this->insertProduct('D-MAL');

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->em->getConnection()->executeStatement("UPDATE market_product SET dimensions_unit = 'yd' WHERE sku = 'D-MAL'");
    }

    public function testDownRemovesTheColumn(): void
    {
        $this->migrate('up');
        $this->assertTrue($this->hasColumn());

        $this->migrate('down');

        $this->assertFalse($this->hasColumn());
    }
}
