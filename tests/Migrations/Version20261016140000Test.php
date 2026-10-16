<?php

namespace App\Tests\Migrations;

use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261016140000;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2) . '/migrations/Version20261016140000.php';

/**
 * COMR-193/194: las tablas de existencias y el libro inmutable de movimientos.
 *
 * @covers \DoctrineMigrations\Version20261016140000
 */
class Version20261016140000Test extends ProviderFunctionalTestCase
{
    private function warehouse(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM market_warehouse WHERE is_main');
    }

    private function line(int $onHand, int $reserved): void
    {
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_stock_line (warehouse_id, item_type, item_id, on_hand, reserved, safety, updated_at) VALUES (:w, 'product', 1, :o, :r, 0, NOW())",
            ['w' => $this->warehouse(), 'o' => $onHand, 'r' => $reserved],
        );
    }

    private function movement(): void
    {
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_stock_movement (warehouse_id, item_type, item_id, type, quantity, on_hand_after, reason, comment, actor_name, owner_cost_center_id, custodian_cost_center_id, created_at)
             VALUES (:w, 'product', 1, 'adjustment', 5, 5, 'count', 'Conteo', 'ana', 1, 1, NOW())",
            ['w' => $this->warehouse()],
        );
    }

    public function testALineKeepsOnHandAtOrAboveReserved(): void
    {
        $this->line(10, 10);
        $this->assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT count(*) FROM market_stock_line'));
    }

    public function testTheBaseRejectsReservedAboveOnHand(): void
    {
        $this->expectException(DbalException::class);
        $this->line(5, 6);
    }

    public function testTheBaseRejectsANegativeQuantity(): void
    {
        $this->expectException(DbalException::class);
        $this->line(-1, 0);
    }

    public function testAMovementCannotBeModified(): void
    {
        $this->movement();

        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement("UPDATE market_stock_movement SET comment = 'otro'");
    }

    public function testAMovementCannotBeDeleted(): void
    {
        $this->movement();

        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement('DELETE FROM market_stock_movement');
    }

    public function testAMovementOfZeroIsRejected(): void
    {
        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement(
            "INSERT INTO market_stock_movement (warehouse_id, item_type, item_id, type, quantity, on_hand_after, reason, comment, actor_name, owner_cost_center_id, custodian_cost_center_id, created_at)
             VALUES (:w, 'product', 1, 'adjustment', 0, 5, 'count', 'Conteo', 'ana', 1, 1, NOW())",
            ['w' => $this->warehouse()],
        );
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $connection = $this->em->getConnection();
        $migration = new Version20261016140000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }

        $this->assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM market_stock_movement'));
    }

    public function testItHasADescription(): void
    {
        $this->assertNotSame('', (new Version20261016140000($this->em->getConnection(), new NullLogger()))->getDescription());
    }
}
