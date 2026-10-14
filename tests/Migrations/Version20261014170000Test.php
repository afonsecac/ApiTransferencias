<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261014170000;
use Psr\Log\NullLogger;

// Las migraciones no están en el autoload de Composer.
require_once dirname(__DIR__, 2) . '/migrations/Version20261014170000.php';

/**
 * COMR-273: tabla del producto base. La migración es idempotente y la base refuerza el estado, el contenido positivo, el
 * sku único y el vendedor referenciado.
 *
 * @covers \DoctrineMigrations\Version20261014170000
 */
class Version20261014170000Test extends ProviderFunctionalTestCase
{
    private function runMigration(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261014170000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function insert(string $sku, string $status = 'draft', string $content = '500', ?int $sellerId = null): void
    {
        $sellerId ??= (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_seller WHERE is_platform');
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_product (sku, seller_id, status, texts, content_base, content_original, content_unit, delivery_kind, delivery_unit, created_at, updated_at)
             VALUES (:sku, :seller, :status, '{}', :content, :content, 'g', 'weight', 'g', NOW(), NOW())",
            ['sku' => $sku, 'seller' => $sellerId, 'status' => $status, 'content' => $content],
        );
    }

    public function testRunningItAgainOnAnExistingTableChangesNothing(): void
    {
        $this->insert('YA-EXISTE');

        $this->runMigration();

        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT count(*) FROM market_product WHERE sku = 'YA-EXISTE'"));
    }

    public function testTheDatabaseOnlyAcceptsTheThreeStatuses(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_status');

        $this->insert('X-1', 'archived');
    }

    public function testTheContentMustBePositive(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('ck_market_product_content_positive');

        $this->insert('X-2', 'draft', '0');
    }

    public function testTheSellerMustExist(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('fk_market_product_seller');

        $this->insert('X-3', 'draft', '500', 987654);
    }

    public function testTheSkuIsUnique(): void
    {
        $this->insert('X-4');

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->insert('X-4');
    }

    public function testItHasADescription(): void
    {
        $migration = new Version20261014170000($this->em->getConnection(), new NullLogger());

        $this->assertNotSame('', $migration->getDescription());
    }
}
