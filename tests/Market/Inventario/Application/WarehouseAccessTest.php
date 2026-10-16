<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Application\WarehouseAccess;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-288: a qué almacenes llega un usuario y con qué permisos. Es la única fuente del aislamiento entre almacenes: el Voter y
 * los casos de uso del socio preguntan aquí.
 *
 * @covers \App\Market\Inventario\Application\WarehouseAccess
 */
final class WarehouseAccessTest extends TestCase
{
    private InMemoryWarehouseRepository $warehouses;
    private InMemoryWarehouseUserRepository $links;
    private int $a;
    private int $b;

    protected function setUp(): void
    {
        $this->warehouses = new InMemoryWarehouseRepository();
        $a = Warehouse::main(new Slug('almacen-a'), 'Almacén A', 1, 20, 11);
        $b = Warehouse::main(new Slug('almacen-b'), 'Almacén B', 1, 20, 11);
        $this->warehouses->save($a);
        $this->warehouses->save($b);
        $this->a = (int) $a->id();
        $this->b = (int) $b->id();
        $this->links = new InMemoryWarehouseUserRepository();
    }

    private function access(): WarehouseAccess
    {
        return new WarehouseAccess($this->warehouses, $this->links);
    }

    private function link(int $userId, int $warehouseId, WarehouseUserPermissions $permissions, bool $active = true): void
    {
        $link = WarehouseUser::link($userId, $warehouseId, $permissions);
        if (!$active) {
            $link->deactivate();
        }
        $this->links->save($link);
    }

    public function testAUserOnlyReachesTheWarehousesItIsLinkedTo(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(stock: true));
        $this->link(6, $this->b, new WarehouseUserPermissions(stock: true));

        $mine = $this->access()->warehousesOf(5);

        $this->assertSame(['Almacén A'], array_map(static fn ($w) => $w->warehouse->name(), $mine));
        $this->assertTrue($mine[0]->permissions->stock);
    }

    public function testAUserLinkedToTwoWarehousesGetsEachWithItsOwnPermissions(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(stock: true));
        $this->link(5, $this->b, new WarehouseUserPermissions(deliveries: true));

        $mine = $this->access()->warehousesOf(5);

        $this->assertCount(2, $mine);
        $this->assertTrue($this->access()->allows(5, $this->a, 'stock'));
        $this->assertFalse($this->access()->allows(5, $this->a, 'deliveries'));
        $this->assertTrue($this->access()->allows(5, $this->b, 'deliveries'));
        $this->assertFalse($this->access()->allows(5, $this->b, 'stock'));
    }

    public function testADeactivatedLinkGivesNoAccessAtAll(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(stock: true), active: false);

        $this->assertSame([], $this->access()->warehousesOf(5));
        $this->assertFalse($this->access()->allows(5, $this->a, 'stock'));
    }

    public function testAWarehouseWithoutALinkIsNeverAllowed(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(stock: true));

        $this->assertFalse($this->access()->allows(5, $this->b, 'stock'));
        $this->assertFalse($this->access()->allows(99, $this->a, 'stock'));
        $this->assertFalse($this->access()->allows(5, 12345, 'stock'));
    }

    public function testAnUnknownPermissionIsNeverAllowed(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(stock: true, assembly: true, transfers: true, deliveries: true));

        $this->assertFalse($this->access()->allows(5, $this->a, 'prices'));
        $this->assertFalse($this->access()->allows(5, $this->a, ''));
    }

    public function testAnyPermissionOfTheLinkCanBeAskedFor(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(assembly: true, transfers: true));

        $this->assertTrue($this->access()->allows(5, $this->a, 'assembly'));
        $this->assertTrue($this->access()->allows(5, $this->a, 'transfers'));
        $this->assertFalse($this->access()->allows(5, $this->a, 'stock'));
    }

    public function testSeeingAWarehouseNeedsAnActiveLinkToIt(): void
    {
        $this->link(5, $this->a, new WarehouseUserPermissions(deliveries: true));
        $this->link(6, $this->a, new WarehouseUserPermissions(stock: true), active: false);

        $this->assertTrue($this->access()->canSee(5, $this->a));
        $this->assertFalse($this->access()->canSee(5, $this->b));
        $this->assertFalse($this->access()->canSee(6, $this->a));
    }
}
