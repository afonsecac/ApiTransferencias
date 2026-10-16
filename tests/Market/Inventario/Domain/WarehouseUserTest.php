<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Domain;

use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use PHPUnit\Framework\TestCase;

final class WarehouseUserTest extends TestCase
{
    public function testItLinksAUserToAWarehouseWithPermissionsAndStartsActive(): void
    {
        $link = WarehouseUser::link(7, 3, new WarehouseUserPermissions(stock: true, deliveries: true));

        $this->assertSame([7, 3, true], [$link->userId(), $link->warehouseId(), $link->isActive()]);
        $this->assertTrue($link->permissions()->stock);
        $this->assertFalse($link->permissions()->assembly);
        $this->assertTrue($link->permissions()->deliveries);
    }

    public function testItNeedsAtLeastOnePermission(): void
    {
        $this->expectException(InvalidWarehouseUser::class);
        WarehouseUser::link(7, 3, new WarehouseUserPermissions());
    }

    public function testPermissionsChangeButNeverToNone(): void
    {
        $link = WarehouseUser::link(7, 3, new WarehouseUserPermissions(stock: true));
        $link->changePermissions(new WarehouseUserPermissions(assembly: true, transfers: true));

        $this->assertFalse($link->permissions()->stock);
        $this->assertTrue($link->permissions()->transfers);

        $this->expectException(InvalidWarehouseUser::class);
        $link->changePermissions(new WarehouseUserPermissions());
    }

    public function testItDeactivatesAndReactivatesWithoutBeingDeleted(): void
    {
        $link = WarehouseUser::link(7, 3, new WarehouseUserPermissions(stock: true));
        $link->deactivate();
        $this->assertFalse($link->isActive());
        $link->activate();
        $this->assertTrue($link->isActive());
    }
}
