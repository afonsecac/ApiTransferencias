<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Domain;

use App\Market\Inventario\Domain\InvalidWarehouse;
use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\PartnerPermissions;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseIncomplete;
use App\Market\Inventario\Domain\WarehouseStatus;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    private function draft(string $name = 'Almacén Boyeros'): Warehouse
    {
        return Warehouse::draft(new Slug('almacen-boyeros'), $name);
    }

    private function complete(): Warehouse
    {
        $warehouse = $this->draft();
        $warehouse->assignOwner(3, 7);
        $warehouse->locate(11);

        return $warehouse;
    }

    public function testItIsBornADraftWithOnlyANameAndASlug(): void
    {
        $warehouse = $this->draft();

        $this->assertSame(WarehouseStatus::Draft, $warehouse->status());
        $this->assertFalse($warehouse->isActive());
        $this->assertFalse($warehouse->isMain());
        $this->assertNull($warehouse->orgUnitId());
        $this->assertNull($warehouse->municipalityId());
        $this->assertSame('almacen-boyeros', $warehouse->slug()->value());
        $this->assertEquals(PartnerPermissions::none(), $warehouse->permissions());
    }

    public function testItNeedsANameWithALimit(): void
    {
        $this->expectException(InvalidWarehouse::class);
        $this->draft('   ');
    }

    public function testTheNameHasALimit(): void
    {
        $this->expectException(InvalidWarehouse::class);
        $this->draft(str_repeat('N', 161));
    }

    public function testTheAddressIsOptionalAndBlankBecomesNull(): void
    {
        $warehouse = $this->draft();
        $warehouse->changeAddress('  Calle 1  ');
        $this->assertSame('Calle 1', $warehouse->address());
        $warehouse->changeAddress('   ');
        $this->assertNull($warehouse->address());
    }

    public function testAnIncompleteDraftListsWhatIsMissing(): void
    {
        $this->assertSame(['owner', 'cost_center', 'municipality'], $this->draft()->missingForActivation());

        $warehouse = $this->draft();
        $warehouse->assignOwner(3, null);
        $this->assertSame(['cost_center', 'municipality'], $warehouse->missingForActivation());
    }

    public function testActivatingAnIncompleteWarehouseIsRejectedWithTheDetail(): void
    {
        try {
            $this->draft()->activate();
            $this->fail('debía rechazarse');
        } catch (WarehouseIncomplete $e) {
            $this->assertSame(['owner', 'cost_center', 'municipality'], $e->missing());
        }
    }

    public function testACompleteDraftActivates(): void
    {
        $warehouse = $this->complete();
        $warehouse->activate();

        $this->assertSame(WarehouseStatus::Active, $warehouse->status());
        $this->assertTrue($warehouse->isActive());
    }

    public function testItDeactivatesAndReactivatesButNeverGoesBackToDraft(): void
    {
        $warehouse = $this->complete();
        $warehouse->activate();
        $warehouse->deactivate();
        $this->assertSame(WarehouseStatus::Inactive, $warehouse->status());

        $warehouse->activate();
        $this->assertSame(WarehouseStatus::Active, $warehouse->status());
    }

    public function testOnlyAnActiveWarehouseCanBeDeactivated(): void
    {
        $this->expectException(WarehouseTransitionInvalid::class);
        $this->complete()->deactivate();
    }

    public function testAnActiveWarehouseCannotBeActivatedAgain(): void
    {
        $warehouse = $this->complete();
        $warehouse->activate();

        $this->expectException(WarehouseTransitionInvalid::class);
        $warehouse->activate();
    }

    public function testOnlyADraftCanBeDiscarded(): void
    {
        $warehouse = $this->complete();
        $this->assertTrue($warehouse->canBeDiscarded());
        $warehouse->activate();
        $this->assertFalse($warehouse->canBeDiscarded());
        $warehouse->deactivate();
        $this->assertFalse($warehouse->canBeDiscarded());
    }

    public function testTheMainWarehouseIsBornActiveAndProtected(): void
    {
        $main = Warehouse::main(new Slug('principal'), 'Almacén principal', 1, 2, 11);

        $this->assertTrue($main->isMain());
        $this->assertTrue($main->isActive());
        $this->assertFalse($main->canBeDiscarded());

        $this->expectException(MainWarehouseProtected::class);
        $main->deactivate();
    }

    public function testTheMainWarehouseKeepsItsOwner(): void
    {
        $main = Warehouse::main(new Slug('principal'), 'Almacén principal', 1, 2, 11);
        $main->assignOwner(1, 2);

        $this->expectException(MainWarehouseProtected::class);
        $main->assignOwner(9, 2);
    }

    public function testItCanBeRenamedAndRelocated(): void
    {
        $warehouse = $this->complete();
        $warehouse->rename('Nuevo nombre');
        $warehouse->locate(12);

        $this->assertSame('Nuevo nombre', $warehouse->name());
        $this->assertSame(12, $warehouse->municipalityId());
        $this->assertSame('almacen-boyeros', $warehouse->slug()->value(), 'el identificador no cambia');
    }

    public function testChangingTheOwnerWithoutACostCenterClearsTheOldOne(): void
    {
        $warehouse = $this->complete();
        $warehouse->assignOwner(4, null);

        $this->assertSame(4, $warehouse->orgUnitId());
        $this->assertNull($warehouse->costCenterId());
    }
}
