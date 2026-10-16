<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Application\AdjustStock;
use App\Market\Inventario\Application\ListStock;
use App\Market\Inventario\Application\ListStockMovements;
use App\Market\Inventario\Application\SetSafetyStock;
use App\Market\Inventario\Domain\InvalidStock;
use App\Market\Inventario\Domain\StockActor;
use App\Market\Inventario\Domain\StockBelowReserved;
use App\Market\Inventario\Domain\StockLineNotFound;
use App\Market\Inventario\Domain\StockProduct;
use App\Market\Inventario\Domain\StockProductInvalid;
use App\Market\Inventario\Domain\StockWarehouseInvalid;
use App\Market\Inventario\Domain\Warehouse;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

final class StockUseCasesTest extends TestCase
{
    private InMemoryStockLedger $ledger;
    private InMemoryWarehouseRepository $warehouses;
    private FakeStockProducts $products;
    private StockActor $actor;

    protected function setUp(): void
    {
        $this->ledger = new InMemoryStockLedger();
        $this->warehouses = new InMemoryWarehouseRepository();
        $this->products = new FakeStockProducts();
        $this->actor = new StockActor(9, 'ana@x.test');
        // 1 = principal activo (centro 20), 2 = borrador
        $main = Warehouse::main(new Slug('principal'), 'Principal', 1, 20, 11);
        $this->warehouses->save($main);
        $this->warehouses->save(Warehouse::draft(new Slug('borrador'), 'Borrador'));
        $this->products->add(new StockProduct(40, 'ARROZ-1', 'Arroz 1 kg', 5, 'Garajito', 50, true));
        $this->products->add(new StockProduct(41, 'GIFT-1', 'Tarjeta', 5, 'Garajito', 50, false));
    }

    private function adjust(): AdjustStock
    {
        return new AdjustStock($this->ledger, $this->warehouses, $this->products);
    }

    private function details(): ListStock
    {
        return new ListStock($this->ledger, $this->warehouses, $this->products);
    }

    /** Cambia lo en mano en `$delta` (el caso de uso recibe el nuevo valor en mano, como el tablero). */
    private function do(int $delta, string $reason = 'count', string $comment = 'Conteo del lunes', int $warehouse = 1, int $product = 40): \App\Market\Inventario\Application\StockLineDetails
    {
        $current = $this->ledger->find($warehouse, \App\Market\Inventario\Domain\StockItemType::Product, $product)?->onHand() ?? 0;

        return $this->set($current + $delta, $reason, $comment, $warehouse, $product);
    }

    private function set(int $newOnHand, string $reason = 'count', string $comment = 'Conteo del lunes', int $warehouse = 1, int $product = 40): \App\Market\Inventario\Application\StockLineDetails
    {
        return ($this->adjust())($warehouse, 'product', $product, $newOnHand, $reason, $comment, null, $this->actor);
    }

    public function testThePositiveAdjustmentOfAMissingLineCreatesItAndRecordsTheMovement(): void
    {
        $details = $this->do(10, 'initial', 'Existencia inicial');

        $this->assertSame(10, $details->line->onHand());
        $this->assertCount(1, $this->ledger->movements);
        $movement = $this->ledger->movements[0];
        $this->assertSame(10, $movement->quantity());
        $this->assertSame(10, $movement->onHandAfter());
        $this->assertSame('initial', $movement->reason()->value);
        $this->assertSame([9, 'ana@x.test'], [$movement->actorId(), $movement->actorName()]);
    }

    public function testTheMovementCarriesTheOwnerAndCustodianCostCenters(): void
    {
        $this->do(5);

        $movement = $this->ledger->movements[0];
        $this->assertSame(50, $movement->ownerCostCenterId(), 'el propietario es el vendedor del producto');
        $this->assertSame(20, $movement->custodianCostCenterId(), 'el custodio es el dueño del almacén');
    }

    public function testASecondAdjustmentUpdatesTheSameLine(): void
    {
        $this->do(10);
        $details = $this->do(-4, 'damage', 'Cajas mojadas');

        $this->assertSame(6, $details->line->onHand());
        $this->assertCount(1, $this->ledger->lines);
        $this->assertCount(2, $this->ledger->movements);
    }

    public function testItCannotLeaveTheStockBelowWhatIsReservedAndNothingIsRecorded(): void
    {
        $this->do(10);
        $this->ledger->find(1, \App\Market\Inventario\Domain\StockItemType::Product, 40)?->reserve(8);

        try {
            $this->do(-5, 'loss', 'Se perdieron');
            $this->fail('debía rechazarse');
        } catch (StockBelowReserved) {
            $this->assertCount(1, $this->ledger->movements);
            $this->assertSame(10, $this->ledger->find(1, \App\Market\Inventario\Domain\StockItemType::Product, 40)?->onHand());
        }
    }

    public function testTheNewValueCannotBeNegative(): void
    {
        $this->expectException(InvalidStock::class);
        $this->set(-1, 'loss', 'No había nada');
    }

    public function testTheNewOnHandValueIsWhatTheLineEndsWithAndTheMovementRecordsTheDifference(): void
    {
        $this->set(120, 'initial', 'Existencia inicial');

        $details = $this->set(116, 'damage', '4 bolsas rotas en el pasillo 3');

        $this->assertSame(116, $details->line->onHand());
        $movement = $this->ledger->movements[1];
        $this->assertSame(-4, $movement->quantity());
        $this->assertSame(116, $movement->onHandAfter());
    }

    public function testASetValueEqualToTheCurrentOneChangesNothingAndIsRejected(): void
    {
        $this->set(10);
        $before = count($this->ledger->movements);

        try {
            $this->set(10);
            $this->fail('debía rechazarse');
        } catch (InvalidStock) {
            $this->assertCount($before, $this->ledger->movements);
        }
    }

    public function testTheNewValueCannotBeBelowWhatIsReserved(): void
    {
        $this->set(120);
        $this->ledger->find(1, \App\Market\Inventario\Domain\StockItemType::Product, 40)?->reserve(18);

        $this->expectException(StockBelowReserved::class);
        $this->set(17);
    }

    public function testTheReasonAndTheCommentAreMandatory(): void
    {
        foreach ([['count', ''], ['count', '  '], ['inventado', 'Un comentario'], ['', 'Un comentario']] as [$reason, $comment]) {
            try {
                $this->do(1, $reason, $comment);
                $this->fail('debía rechazar ' . $reason . '/' . $comment);
            } catch (InvalidStock) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->ledger->movements);
    }

    public function testADraftWarehouseAdmitsNoStock(): void
    {
        $this->expectException(StockWarehouseInvalid::class);
        $this->do(5, warehouse: 2);
    }

    public function testAnUnknownWarehouseIsRejected(): void
    {
        $this->expectException(StockWarehouseInvalid::class);
        $this->do(5, warehouse: 99);
    }

    public function testADigitalOrUnknownProductHasNoStock(): void
    {
        foreach ([41, 99] as $product) {
            try {
                $this->do(5, product: $product);
                $this->fail('debía rechazar el producto ' . $product);
            } catch (StockProductInvalid) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testACombosStockArrivesWithTheCombosStory(): void
    {
        $this->expectException(StockProductInvalid::class);
        ($this->adjust())(1, 'combo', 7, 5, 'count', 'Un combo', null, $this->actor);
    }

    public function testTheSafetyStockIsSetOnAnExistingLine(): void
    {
        $this->do(10);

        $details = (new SetSafetyStock($this->ledger, $this->warehouses, $this->products))(1, 'product', 40, 3);

        $this->assertSame(3, $details->line->safety());
        $this->assertSame(7, $details->available);
    }

    public function testTheSafetyStockOfAMissingLineIsRejected(): void
    {
        $this->expectException(StockLineNotFound::class);
        (new SetSafetyStock($this->ledger, $this->warehouses, $this->products))(1, 'product', 40, 3);
    }

    public function testTheAvailableIsZeroInAWarehouseThatIsNotActiveButReservesStillShow(): void
    {
        $this->do(10);
        $this->ledger->find(1, \App\Market\Inventario\Domain\StockItemType::Product, 40)?->reserve(4);
        $warehouse = $this->warehouses->findById(1);
        $this->assertNotNull($warehouse);
        $line = ($this->details())(null, null, null, false)[0];
        $this->assertSame(6, $line->available);

        // Un almacén inactivo: reconstruimos uno inactivo con el mismo id para la lectura.
        $inactive = Warehouse::draft(new Slug('inact'), 'Inactivo');
        $inactive->assignOwner(1, 20);
        $inactive->locate(11);
        $inactive->activate();
        $inactive->deactivate();
        $this->warehouses->save($inactive);
        $this->ledger->save(\App\Market\Inventario\Domain\StockLine::open((int) $inactive->id(), \App\Market\Inventario\Domain\StockItemType::Product, 40));
        $this->ledger->find((int) $inactive->id(), \App\Market\Inventario\Domain\StockItemType::Product, 40)?->adjust(9);
        $this->ledger->find((int) $inactive->id(), \App\Market\Inventario\Domain\StockItemType::Product, 40)?->reserve(2);

        $rows = ($this->details())((int) $inactive->id(), null, null, false);

        $this->assertSame(0, $rows[0]->available);
        $this->assertSame(2, $rows[0]->line->reserved());
        $this->assertFalse($rows[0]->warehouseActive);
    }

    public function testItListsWithFiltersByWarehouseTextTypeAndLowStock(): void
    {
        $this->products->add(new StockProduct(42, 'ACEITE-1', 'Aceite 1 L', 5, 'Garajito', 50, true));
        $this->do(10, product: 40);
        $this->do(2, product: 42);
        (new SetSafetyStock($this->ledger, $this->warehouses, $this->products))(1, 'product', 42, 5);
        $list = $this->details();

        $this->assertCount(2, $list(null, null, null, false));
        $this->assertCount(1, $list(1, 'aceite', null, false));
        $this->assertCount(2, $list(null, null, 'product', false));
        $this->assertCount(0, $list(null, null, 'combo', false));
        $low = $list(null, null, null, true);
        $this->assertSame(['ACEITE-1'], array_map(static fn ($d) => $d->product?->sku, $low));
        $this->assertTrue($low[0]->low);
    }

    public function testTheMovementsListIsNewestFirstAndCarriesWhoWhenAndWhy(): void
    {
        $this->do(10, 'initial', 'Existencia inicial');
        $this->do(-2, 'damage', 'Cajas mojadas');

        $movements = (new ListStockMovements($this->ledger, $this->warehouses, $this->products))(null, null, 50);

        $this->assertSame([-2, 10], array_map(static fn ($m) => $m->movement->quantity(), $movements));
        $this->assertSame('Principal', $movements[0]->warehouse?->name());
        $this->assertSame('Arroz 1 kg', $movements[0]->product?->name);
    }
}
