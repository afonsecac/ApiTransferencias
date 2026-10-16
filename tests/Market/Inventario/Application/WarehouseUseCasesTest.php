<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Application\ActivateWarehouse;
use App\Market\Inventario\Application\CreateWarehouse;
use App\Market\Inventario\Application\DeactivateWarehouse;
use App\Market\Inventario\Application\DiscardWarehouse;
use App\Market\Inventario\Application\GetWarehouse;
use App\Market\Inventario\Application\ListWarehouses;
use App\Market\Inventario\Application\SellableWarehouseIds;
use App\Market\Inventario\Application\UpdateWarehouse;
use App\Market\Inventario\Application\WarehouseInput;
use App\Market\Inventario\Domain\MainWarehouseProtected;
use App\Market\Inventario\Domain\PartnerPermissions;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseAlreadyExists;
use App\Market\Inventario\Domain\WarehouseIncomplete;
use App\Market\Inventario\Domain\WarehouseMunicipalityInvalid;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseOwnerInvalid;
use App\Market\Inventario\Domain\WarehouseTransitionInvalid;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

final class WarehouseUseCasesTest extends TestCase
{
    private InMemoryWarehouseRepository $repo;
    private FakeWarehouseOwners $owners;
    private FakeWarehouseMunicipalities $municipalities;

    protected function setUp(): void
    {
        $this->repo = new InMemoryWarehouseRepository();
        $this->owners = new FakeWarehouseOwners();
        $this->owners->add(1, 'Plataforma', ['platform', 'seller'], 10);
        $this->owners->add(2, 'Socio SL', ['warehouse_partner'], 20, 21);
        $this->owners->add(3, 'Vendedor SA', ['seller'], 30);
        $this->owners->add(4, 'Ambos', ['platform', 'warehouse_partner'], 40);
        $this->municipalities = new FakeWarehouseMunicipalities();
        $this->municipalities->add(11, 'Boyeros');
        $this->municipalities->add(12, 'Playa');
        $this->municipalities->add(13, 'Cerro', selectable: false);
    }

    private function create(string $name = 'Almacén Norte', ?string $slug = null): Warehouse
    {
        return (new CreateWarehouse($this->repo))($name, $slug)->warehouse;
    }

    private function update(): UpdateWarehouse
    {
        return new UpdateWarehouse($this->repo, $this->owners, $this->municipalities);
    }

    private function input(?int $owner = 2, ?int $center = null, ?int $municipality = 11, ?string $name = null, ?PartnerPermissions $permissions = null): WarehouseInput
    {
        return new WarehouseInput($name ?? 'Almacén Norte', null, $municipality, $owner, $center, $permissions ?? new PartnerPermissions(true, true, false));
    }

    private function activate(): ActivateWarehouse
    {
        return new ActivateWarehouse($this->repo, $this->owners, $this->municipalities);
    }

    public function testItCreatesADraftAndProposesTheSlugFromTheName(): void
    {
        $warehouse = $this->create('Almacén Norte');

        $this->assertSame('almacen-norte', $warehouse->slug()->value());
        $this->assertFalse($warehouse->isActive());
        $this->assertNotNull($warehouse->id());
    }

    public function testTheSecondWarehouseWithTheSameNameTakesASuffix(): void
    {
        $this->create('Almacén Norte');

        $this->assertSame('almacen-norte-2', $this->create('Almacén Norte')->slug()->value());
    }

    public function testAnExplicitSlugThatIsTakenIsRejected(): void
    {
        $this->create('Uno', 'sitio');

        $this->expectException(WarehouseAlreadyExists::class);
        $this->create('Dos', 'sitio');
    }

    public function testItUpdatesTheDataWithOwnerAndTheirPrincipalCostCenter(): void
    {
        $warehouse = $this->create();

        $details = ($this->update())((int) $warehouse->id(), $this->input());

        $this->assertSame(2, $details->warehouse->orgUnitId());
        $this->assertSame(20, $details->warehouse->costCenterId());
        $this->assertSame(11, $details->warehouse->municipalityId());
        $this->assertSame('partner', $details->regime);
        $this->assertTrue($details->warehouse->permissions()->setsCoverage);
    }

    public function testThePlatformWinsWhenTheUnitIsBothPlatformAndPartner(): void
    {
        $warehouse = $this->create();

        $details = ($this->update())((int) $warehouse->id(), $this->input(owner: 4));

        $this->assertSame('platform', $details->regime);
    }

    public function testThePermissionsAreIgnoredOnAPlatformWarehouse(): void
    {
        $warehouse = $this->create();

        $details = ($this->update())((int) $warehouse->id(), $this->input(owner: 1));

        $this->assertEquals(PartnerPermissions::none(), $details->warehouse->permissions());
    }

    public function testAnOwnerThatIsNeitherPlatformNorPartnerIsRejected(): void
    {
        $warehouse = $this->create();

        $this->expectException(WarehouseOwnerInvalid::class);
        ($this->update())((int) $warehouse->id(), $this->input(owner: 3));
    }

    public function testAnUnknownOwnerIsRejected(): void
    {
        $warehouse = $this->create();

        $this->expectException(WarehouseOwnerInvalid::class);
        ($this->update())((int) $warehouse->id(), $this->input(owner: 99));
    }

    public function testACostCenterOfAnotherOwnerOrInactiveIsRejected(): void
    {
        $warehouse = $this->create();

        foreach ([10, 21] as $center) {
            try {
                ($this->update())((int) $warehouse->id(), $this->input(owner: 2, center: $center));
                $this->fail('debía rechazarse el centro ' . $center);
            } catch (WarehouseOwnerInvalid) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnActiveCostCenterOfTheOwnerIsAccepted(): void
    {
        $warehouse = $this->create();

        $details = ($this->update())((int) $warehouse->id(), $this->input(owner: 2, center: 20));

        $this->assertSame(20, $details->warehouse->costCenterId());
    }

    public function testTheMunicipalityMustBeSelectableWhenItIsChosen(): void
    {
        $warehouse = $this->create();

        $this->expectException(WarehouseMunicipalityInvalid::class);
        ($this->update())((int) $warehouse->id(), $this->input(municipality: 13));
    }

    public function testAnUnknownMunicipalityIsRejected(): void
    {
        $warehouse = $this->create();

        $this->expectException(WarehouseMunicipalityInvalid::class);
        ($this->update())((int) $warehouse->id(), $this->input(municipality: 99));
    }

    public function testAMunicipalityDeactivatedLaterDoesNotBlockRenaming(): void
    {
        $warehouse = $this->create();
        ($this->update())((int) $warehouse->id(), $this->input());
        $this->municipalities->items[11] = new \App\Market\Inventario\Domain\WarehouseMunicipality(11, 'boyeros', 'Boyeros', 'La Habana', false);

        $details = ($this->update())((int) $warehouse->id(), $this->input(name: 'Otro nombre'));

        $this->assertSame('Otro nombre', $details->warehouse->name());
        $this->assertSame(11, $details->warehouse->municipalityId());
    }

    public function testUpdatingAnUnknownWarehouseFails(): void
    {
        $this->expectException(WarehouseNotFound::class);
        ($this->update())(99, $this->input());
    }

    public function testActivatingAnIncompleteDraftListsWhatIsMissing(): void
    {
        $warehouse = $this->create();

        try {
            ($this->activate())((int) $warehouse->id());
            $this->fail('debía rechazarse');
        } catch (WarehouseIncomplete $e) {
            $this->assertSame(['owner', 'cost_center', 'municipality'], $e->missing());
        }
    }

    public function testActivationRevalidatesTheOwnerAndTheMunicipality(): void
    {
        $warehouse = $this->create();
        ($this->update())((int) $warehouse->id(), $this->input());
        $this->municipalities->items[11] = new \App\Market\Inventario\Domain\WarehouseMunicipality(11, 'boyeros', 'Boyeros', 'La Habana', false);

        try {
            ($this->activate())((int) $warehouse->id());
            $this->fail('debía rechazarse');
        } catch (WarehouseIncomplete $e) {
            $this->assertSame(['municipality'], $e->missing());
        }
    }

    public function testACompleteWarehouseActivatesDeactivatesAndReactivates(): void
    {
        $warehouse = $this->create();
        ($this->update())((int) $warehouse->id(), $this->input());
        $id = (int) $warehouse->id();

        $this->assertTrue(($this->activate())($id)->warehouse->isActive());
        $this->assertFalse((new DeactivateWarehouse($this->repo, $this->owners, $this->municipalities))($id)->warehouse->isActive());
        $this->assertTrue(($this->activate())($id)->warehouse->isActive());
    }

    public function testOnlyActiveWarehousesCanSell(): void
    {
        $active = $this->create('Activo');
        ($this->update())((int) $active->id(), $this->input());
        ($this->activate())((int) $active->id());
        $draft = $this->create('Borrador');
        $inactive = $this->create('Inactivo');
        ($this->update())((int) $inactive->id(), $this->input());
        ($this->activate())((int) $inactive->id());
        (new DeactivateWarehouse($this->repo, $this->owners, $this->municipalities))((int) $inactive->id());

        $this->assertSame([(int) $active->id()], (new SellableWarehouseIds($this->repo))());
        $this->assertNotContains((int) $draft->id(), (new SellableWarehouseIds($this->repo))());
    }

    public function testTheMainWarehouseCannotBeDeactivatedNorDiscarded(): void
    {
        $main = Warehouse::main(new Slug('principal'), 'Principal', 1, 10, 11);
        $this->repo->save($main);

        try {
            (new DeactivateWarehouse($this->repo, $this->owners, $this->municipalities))((int) $main->id());
            $this->fail('debía rechazarse');
        } catch (MainWarehouseProtected) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(WarehouseTransitionInvalid::class);
        (new DiscardWarehouse($this->repo))((int) $main->id());
    }

    public function testADraftIsDiscardedButAnActivatedOneNever(): void
    {
        $draft = $this->create('Borrador');
        (new DiscardWarehouse($this->repo))((int) $draft->id());
        $this->assertNull($this->repo->findById((int) $draft->id()));

        $used = $this->create('Usado');
        ($this->update())((int) $used->id(), $this->input());
        ($this->activate())((int) $used->id());
        (new DeactivateWarehouse($this->repo, $this->owners, $this->municipalities))((int) $used->id());

        $this->expectException(WarehouseTransitionInvalid::class);
        (new DiscardWarehouse($this->repo))((int) $used->id());
    }

    public function testItListsWithFiltersByRegimeStatusAndText(): void
    {
        $a = $this->create('Alfa');
        ($this->update())((int) $a->id(), $this->input(owner: 1, municipality: 11, name: 'Alfa'));
        $b = $this->create('Beta');
        ($this->update())((int) $b->id(), $this->input(owner: 2, municipality: 12, name: 'Beta'));
        ($this->activate())((int) $b->id());
        $list = new ListWarehouses($this->repo, $this->owners, $this->municipalities);

        $this->assertCount(2, $list(null, null, null));
        $this->assertSame(['Alfa'], array_map(static fn ($d) => $d->warehouse->name(), $list(null, 'platform', null)));
        $this->assertSame(['Beta'], array_map(static fn ($d) => $d->warehouse->name(), $list(null, null, 'active')));
        $this->assertSame(['Beta'], array_map(static fn ($d) => $d->warehouse->name(), $list('playa', null, null)));
        $this->assertSame(['Alfa'], array_map(static fn ($d) => $d->warehouse->name(), $list('ALFA', null, 'draft')));
    }

    public function testTheDetailsCarryOwnerMunicipalityAndWhatIsMissing(): void
    {
        $warehouse = $this->create();
        ($this->update())((int) $warehouse->id(), $this->input(owner: 2, municipality: null));

        $details = (new GetWarehouse($this->repo, $this->owners, $this->municipalities))((int) $warehouse->id());

        $this->assertSame('Socio SL', $details->owner?->name);
        $this->assertNull($details->municipality);
        $this->assertSame(['municipality'], $details->missing);
    }
}
