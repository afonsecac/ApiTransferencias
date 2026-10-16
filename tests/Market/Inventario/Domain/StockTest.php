<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Domain;

use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockActor;
use App\Market\Inventario\Domain\StockBelowReserved;
use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockMovement;
use App\Market\Inventario\Domain\StockReason;
use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    private function line(int $onHand = 0, int $reserved = 0, int $safety = 0): StockLine
    {
        $line = StockLine::open(3, StockItemType::Product, 40);
        if ($onHand > 0) {
            $line->adjust($onHand);
        }
        if ($reserved > 0) {
            $line->reserve($reserved);
        }
        $line->setSafety($safety);

        return $line;
    }

    public function testALineStartsEmpty(): void
    {
        $line = StockLine::open(3, StockItemType::Product, 40);

        $this->assertSame([0, 0, 0], [$line->onHand(), $line->reserved(), $line->safety()]);
        $this->assertSame(3, $line->warehouseId());
        $this->assertSame(StockItemType::Product, $line->itemType());
        $this->assertSame(40, $line->itemId());
    }

    public function testItAddsAndRemovesStock(): void
    {
        $line = $this->line(10);

        $this->assertSame(15, $line->adjust(5));
        $this->assertSame(12, $line->adjust(-3));
        $this->assertSame(12, $line->onHand());
    }

    public function testItCannotLeaveTheStockBelowWhatIsReserved(): void
    {
        $line = $this->line(10, 4);

        $this->expectException(StockBelowReserved::class);
        $line->adjust(-7);
    }

    public function testItCanLeaveItExactlyAtTheReserved(): void
    {
        $line = $this->line(10, 4);

        $this->assertSame(4, $line->adjust(-6));
    }

    public function testItCannotGoBelowZero(): void
    {
        $this->expectException(StockBelowReserved::class);
        $this->line(2)->adjust(-3);
    }

    public function testAZeroAdjustmentIsNotAnAdjustment(): void
    {
        $this->expectException(InvalidStock::class);
        $this->line(2)->adjust(0);
    }

    public function testTheSafetyStockIsNeverNegative(): void
    {
        $this->expectException(InvalidStock::class);
        $this->line()->setSafety(-1);
    }

    public function testTheAvailableIsOnHandMinusReservedMinusSafetyOnlyWhileTheWarehouseIsActive(): void
    {
        $line = $this->line(20, 5, 3);

        $this->assertSame(12, $line->available(true));
        $this->assertSame(0, $line->available(false), 'un almacén inactivo no vende: el disponible es 0');
        $this->assertSame(5, $line->reserved(), 'las reservas existentes siguen mostrándose');
    }

    public function testTheAvailableIsNeverNegative(): void
    {
        $this->assertSame(0, $this->line(5, 3, 4)->available(true));
    }

    public function testItIsLowWhenWhatIsFreeDoesNotExceedTheSafetyStock(): void
    {
        $this->assertTrue($this->line(8, 3, 5)->isLow());
        $this->assertFalse($this->line(9, 3, 5)->isLow());
        $this->assertTrue($this->line(0, 0, 0)->isLow(), 'sin existencias está bajo mínimos');
    }

    public function testAMovementRecordsTheAdjustmentImmutably(): void
    {
        $line = $this->line(10);
        $before = $line->onHand();
        $line->adjust(-4);
        $movement = StockMovement::adjustment($line, -4, StockReason::Damage, '  Cajas mojadas  ', ' ALB-7 ', new StockActor(9, 'ana@x.test'), 11, 22);

        $this->assertSame(-4, $movement->quantity());
        $this->assertSame($before - 4, $movement->onHandAfter());
        $this->assertSame(StockReason::Damage, $movement->reason());
        $this->assertSame('Cajas mojadas', $movement->comment());
        $this->assertSame('ALB-7', $movement->reference());
        $this->assertSame('adjustment', $movement->type());
        $this->assertSame([9, 'ana@x.test'], [$movement->actorId(), $movement->actorName()]);
        $this->assertSame([11, 22], [$movement->ownerCostCenterId(), $movement->custodianCostCenterId()]);
        $this->assertSame(3, $movement->warehouseId());
        $this->assertSame(40, $movement->itemId());
    }

    public function testAMovementNeedsACommentAndAKnownReason(): void
    {
        foreach (['', '  ', 'ab'] as $comment) {
            try {
                StockMovement::adjustment($this->line(5), 1, StockReason::Count, $comment, null, new StockActor(1, 'a'), 1, 1);
                $this->fail('debía rechazar el comentario "' . $comment . '"');
            } catch (InvalidStock) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNull(StockReason::tryFrom('porque-si'));
    }

    public function testTheCommentAndTheReferenceHaveALimit(): void
    {
        $this->expectException(InvalidStock::class);
        StockMovement::adjustment($this->line(5), 1, StockReason::Count, str_repeat('x', 501), null, new StockActor(1, 'a'), 1, 1);
    }
}
