<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Application\ChangeWarehouseUserPermissions;
use App\Market\Inventario\Application\InviteWarehouseUser;
use App\Market\Inventario\Application\ListWarehouseUsers;
use App\Market\Inventario\Application\ResendWarehouseUserInvitation;
use App\Market\Inventario\Application\SetWarehouseUserActive;
use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\StaffAlreadyActivated;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseNotFound;
use App\Market\Inventario\Domain\WarehouseUserAlreadyLinked;
use App\Market\Inventario\Domain\WarehouseUserNotFound;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

final class WarehouseUserUseCasesTest extends TestCase
{
    private InMemoryWarehouseRepository $warehouses;
    private InMemoryWarehouseUserRepository $links;
    private FakeWarehouseStaff $staff;

    protected function setUp(): void
    {
        $this->warehouses = new InMemoryWarehouseRepository();
        $this->warehouses->save(Warehouse::main(new Slug('principal'), 'Principal', 1, 20, 11));
        $this->warehouses->save(Warehouse::main(new Slug('otro'), 'Otro', 1, 20, 11));
        $this->links = new InMemoryWarehouseUserRepository();
        $this->staff = new FakeWarehouseStaff();
        $this->staff->add(5, 'ana@x.test');
        $this->staff->add(6, 'pendiente@x.test', activated: false);
    }

    private function invite(): InviteWarehouseUser
    {
        return new InviteWarehouseUser($this->warehouses, $this->links, $this->staff);
    }

    private function list(): ListWarehouseUsers
    {
        return new ListWarehouseUsers($this->warehouses, $this->links, $this->staff);
    }

    private function perms(bool $stock = true, bool $assembly = false, bool $transfers = false, bool $deliveries = false): WarehouseUserPermissions
    {
        return new WarehouseUserPermissions($stock, $assembly, $transfers, $deliveries);
    }

    public function testAnExistingUserIsLinkedWithoutCreatingAnotherAccount(): void
    {
        $row = ($this->invite())(1, 'ANA@x.test', null, null, $this->perms(deliveries: true), 9);

        $this->assertSame(5, $row->link->userId());
        $this->assertSame(0, $this->staff->invitations);
        $this->assertSame('active', $row->status);
        $this->assertTrue($row->link->permissions()->deliveries);
    }

    public function testAnUnknownEmailCreatesTheUserAndLeavesItInvited(): void
    {
        $row = ($this->invite())(1, 'nuevo@x.test', 'Luis', 'Gómez', $this->perms(), 9);

        $this->assertSame(1, $this->staff->invitations);
        $this->assertSame('invited', $row->status);
        $this->assertSame('nuevo@x.test', $row->member?->email);
    }

    public function testANewUserNeedsANameToBeInvited(): void
    {
        $this->expectException(InvalidWarehouseUser::class);
        ($this->invite())(1, 'nuevo@x.test', null, null, $this->perms(), 9);
    }

    public function testAnInvalidEmailIsRejected(): void
    {
        $this->expectException(InvalidWarehouseUser::class);
        ($this->invite())(1, 'no-es-un-correo', 'A', 'B', $this->perms(), 9);
    }

    public function testTheSameUserIsNotLinkedTwiceToTheSameWarehouse(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);

        $this->expectException(WarehouseUserAlreadyLinked::class);
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);
    }

    public function testAPersonCanBeInTwoWarehousesWithDifferentPermissions(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(stock: true), 9);
        ($this->invite())(2, 'ana@x.test', null, null, $this->perms(stock: false, transfers: true), 9);

        $this->assertTrue($this->links->find(5, 1)?->permissions()->stock);
        $this->assertFalse($this->links->find(5, 2)?->permissions()->stock);
        $this->assertTrue($this->links->find(5, 2)?->permissions()->transfers);
    }

    public function testAnUnknownWarehouseIsRejected(): void
    {
        $this->expectException(WarehouseNotFound::class);
        ($this->invite())(99, 'ana@x.test', null, null, $this->perms(), 9);
    }

    public function testTheListShowsInvitedActiveAndInactive(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);
        ($this->invite())(1, 'pendiente@x.test', null, null, $this->perms(), 9);
        $this->staff->add(7, 'baja@x.test');
        ($this->invite())(1, 'baja@x.test', null, null, $this->perms(), 9);
        (new SetWarehouseUserActive($this->warehouses, $this->links, $this->staff))(1, 7, false);

        $rows = ($this->list())(1);

        $this->assertSame(['active', 'invited', 'inactive'], array_map(static fn ($r) => $r->status, $rows));
        $this->assertSame('Ana Pérez', $rows[0]->member?->fullName);
    }

    public function testTheListIsOfThatWarehouseOnly(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);

        $this->assertSame([], ($this->list())(2));
    }

    public function testItChangesPermissionsButNotToNone(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);
        $change = new ChangeWarehouseUserPermissions($this->warehouses, $this->links, $this->staff);

        $row = $change(1, 5, $this->perms(stock: false, assembly: true));
        $this->assertTrue($row->link->permissions()->assembly);

        $this->expectException(InvalidWarehouseUser::class);
        $change(1, 5, $this->perms(stock: false));
    }

    public function testItDeactivatesAndReactivatesTheLinkWithoutDeletingIt(): void
    {
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);
        $set = new SetWarehouseUserActive($this->warehouses, $this->links, $this->staff);

        $this->assertSame('inactive', $set(1, 5, false)->status);
        $this->assertSame('active', $set(1, 5, true)->status);
        $this->assertCount(1, $this->links->items);
    }

    public function testChangingAnUnknownLinkFails(): void
    {
        $this->expectException(WarehouseUserNotFound::class);
        (new SetWarehouseUserActive($this->warehouses, $this->links, $this->staff))(1, 5, false);
    }

    public function testItResendsTheInvitationOnlyWhileInvited(): void
    {
        ($this->invite())(1, 'pendiente@x.test', null, null, $this->perms(), 9);
        ($this->invite())(1, 'ana@x.test', null, null, $this->perms(), 9);
        $resend = new ResendWarehouseUserInvitation($this->warehouses, $this->links, $this->staff);

        $resend(1, 6);
        $this->assertSame(1, $this->staff->resends);

        $this->expectException(StaffAlreadyActivated::class);
        $resend(1, 5);
    }
}
