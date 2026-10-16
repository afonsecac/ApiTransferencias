<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Infrastructure;

use App\Entity\User;
use App\Market\Inventario\Application\WarehouseAccess;
use App\Market\Inventario\Domain\Warehouse;
use App\Market\Inventario\Domain\WarehouseUser;
use App\Market\Inventario\Domain\WarehouseUserPermissions;
use App\Market\Inventario\Infrastructure\Security\WarehouseVoter;
use App\Shared\Domain\Slug;
use App\Tests\Market\Inventario\Application\InMemoryWarehouseRepository;
use App\Tests\Market\Inventario\Application\InMemoryWarehouseUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @covers \App\Market\Inventario\Infrastructure\Security\WarehouseVoter
 */
final class WarehouseVoterTest extends TestCase
{
    private int $a;
    private int $b;
    private WarehouseVoter $voter;

    protected function setUp(): void
    {
        $warehouses = new InMemoryWarehouseRepository();
        $a = Warehouse::main(new Slug('almacen-a'), 'Almacén A', 1, 20, 11);
        $b = Warehouse::main(new Slug('almacen-b'), 'Almacén B', 1, 20, 11);
        $warehouses->save($a);
        $warehouses->save($b);
        $this->a = (int) $a->id();
        $this->b = (int) $b->id();
        $links = new InMemoryWarehouseUserRepository();
        $links->save(WarehouseUser::link(5, $this->a, new WarehouseUserPermissions(stock: true)));
        $this->voter = new WarehouseVoter(new WarehouseAccess($warehouses, $links));
    }

    private function token(int $id): UsernamePasswordToken
    {
        $user = new User();
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return new UsernamePasswordToken($user, 'warehouse', []);
    }

    public function testItGrantsWhatTheLinkAllowsInThatWarehouse(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token(5), $this->a, [WarehouseVoter::STOCK]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token(5), $this->a, [WarehouseVoter::VIEW]));
    }

    public function testItDeniesWhatTheLinkDoesNotAllow(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token(5), $this->a, [WarehouseVoter::DELIVERIES]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token(5), $this->a, [WarehouseVoter::TRANSFERS]));
    }

    public function testItDeniesAnyOtherWarehouse(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token(5), $this->b, [WarehouseVoter::STOCK]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token(5), $this->b, [WarehouseVoter::VIEW]));
    }

    public function testItDeniesAnUnlinkedUserAndAnAnonymousToken(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token(99), $this->a, [WarehouseVoter::STOCK]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(new NullToken(), $this->a, [WarehouseVoter::STOCK]));
    }

    public function testItAbstainsFromAttributesAndSubjectsThatAreNotItsOwn(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token(5), $this->a, ['ROLE_ADMIN']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token(5), 'texto', [WarehouseVoter::STOCK]));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token(5), null, [WarehouseVoter::STOCK]));
    }
}
