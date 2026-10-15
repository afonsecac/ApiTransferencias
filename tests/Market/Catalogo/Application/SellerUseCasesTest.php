<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateSeller;
use App\Market\Catalogo\Application\GetPlatformSeller;
use App\Market\Catalogo\Application\GetSeller;
use App\Market\Catalogo\Application\ListSellers;
use App\Market\Catalogo\Application\SetSellerActive;
use App\Market\Catalogo\Application\UpdateSeller;
use App\Market\Catalogo\Domain\InvalidSeller;
use App\Market\Catalogo\Domain\PlatformSellerProtected;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerAlreadyExists;
use App\Market\Catalogo\Domain\SellerCostCenter;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerOrgUnitInvalid;
use App\Market\Catalogo\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\CreateSeller
 * @covers \App\Market\Catalogo\Application\UpdateSeller
 * @covers \App\Market\Catalogo\Application\SetSellerActive
 * @covers \App\Market\Catalogo\Application\GetSeller
 * @covers \App\Market\Catalogo\Application\ListSellers
 * @covers \App\Market\Catalogo\Application\GetPlatformSeller
 */
final class SellerUseCasesTest extends TestCase
{
    private InMemorySellerRepository $sellers;
    private FakeSellerOrgUnits $orgUnits;

    protected function setUp(): void
    {
        $this->sellers = new InMemorySellerRepository();
        $this->orgUnits = new FakeSellerOrgUnits();
        $this->orgUnits->add(7, 'Garajito Foods SL');
        $this->orgUnits->add(8, 'La Jabalina SL');
        $this->orgUnits->add(9, 'Solo comprador', ['buyer']);
    }

    private function create(): CreateSeller
    {
        return new CreateSeller($this->sellers, $this->orgUnits);
    }

    private function platform(): Seller
    {
        $this->orgUnits->add(1, 'Comremit Solutions SL', ['platform', 'seller']);
        $platform = Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma');
        $this->sellers->save($platform);

        return $platform;
    }

    public function testItCreatesASellerChargedToThePrincipalCostCenterOfItsUnit(): void
    {
        $details = ($this->create())(7, 'Garajito');

        $this->assertSame('Garajito', $details->seller->name());
        $this->assertSame('garajito', $details->seller->slug()->value());
        $this->assertSame(70, $details->seller->costCenterId());
        $this->assertFalse($details->seller->isPlatform());
        $this->assertSame('Garajito Foods SL', $details->orgUnit?->name);
        $this->assertSame('PRINCIPAL', $details->costCenter?->code);
    }

    public function testItAcceptsAnExplicitSlugAndCostCenter(): void
    {
        $this->orgUnits->add(7, 'Garajito Foods SL', ['seller'], [
            new SellerCostCenter(70, 'PRINCIPAL', 'Principal', true, true),
            new SellerCostCenter(71, 'RETAIL', 'Retail', false, true),
        ]);

        $details = ($this->create())(7, 'Garajito', 'garajito-retail', 71);

        $this->assertSame('garajito-retail', $details->seller->slug()->value());
        $this->assertSame(71, $details->seller->costCenterId());
    }

    public function testTheUnitMustExist(): void
    {
        $this->expectException(SellerOrgUnitInvalid::class);

        ($this->create())(99, 'Fantasma');
    }

    public function testTheUnitMustHaveTheSellerType(): void
    {
        $this->expectException(SellerOrgUnitInvalid::class);

        ($this->create())(9, 'Solo comprador');
    }

    public function testTheCostCenterMustBelongToTheUnitAndBeActive(): void
    {
        $this->orgUnits->add(8, 'La Jabalina SL', ['seller'], [
            new SellerCostCenter(80, 'PRINCIPAL', 'Principal', true, true),
            new SellerCostCenter(81, 'VIEJO', 'Viejo', false, false),
        ]);

        foreach ([70 /* de otra unidad */, 81 /* inactivo */, 999 /* inexistente */] as $costCenterId) {
            try {
                ($this->create())(8, 'La Jabalina', null, $costCenterId);
                $this->fail("El centro $costCenterId debió rechazarse");
            } catch (SellerOrgUnitInvalid) {
                $this->assertSame([], $this->sellers->sellers);
            }
        }
    }

    public function testAUnitHasAtMostOneSeller(): void
    {
        ($this->create())(7, 'Garajito');

        $this->expectException(SellerAlreadyExists::class);

        ($this->create())(7, 'Otro nombre', 'otro-slug');
    }

    public function testTheSlugIsUnique(): void
    {
        ($this->create())(7, 'Garajito', 'marca');

        $this->expectException(SellerAlreadyExists::class);

        ($this->create())(8, 'La Jabalina', 'marca');
    }

    public function testASellerNeverCreatesAPlatformSellerNorReusesItsSlugOfAnother(): void
    {
        $this->platform();

        $this->expectException(SellerAlreadyExists::class);

        ($this->create())(7, 'Plataforma', 'plataforma');
    }

    public function testItRejectsAnInvalidName(): void
    {
        $this->expectException(InvalidSeller::class);

        ($this->create())(7, '  ');
    }

    public function testItUpdatesTheNameAndTheCostCenterButNotTheSlugNorTheUnit(): void
    {
        $this->orgUnits->add(7, 'Garajito Foods SL', ['seller'], [
            new SellerCostCenter(70, 'PRINCIPAL', 'Principal', true, true),
            new SellerCostCenter(71, 'RETAIL', 'Retail', false, true),
        ]);
        $created = ($this->create())(7, 'Garajito');

        $updated = (new UpdateSeller($this->sellers, $this->orgUnits))((int) $created->seller->id(), 'Garajito Foods', 71);

        $this->assertSame('Garajito Foods', $updated->seller->name());
        $this->assertSame(71, $updated->seller->costCenterId());
        $this->assertSame('garajito', $updated->seller->slug()->value());
        $this->assertSame(7, $updated->seller->orgUnitId());
    }

    public function testUpdatingKeepsTheCostCenterWhenNoneIsGiven(): void
    {
        $created = ($this->create())(7, 'Garajito');

        $updated = (new UpdateSeller($this->sellers, $this->orgUnits))((int) $created->seller->id(), 'Nuevo nombre');

        $this->assertSame(70, $updated->seller->costCenterId());
    }

    public function testUpdatingRejectsACostCenterOfAnotherUnit(): void
    {
        $created = ($this->create())(7, 'Garajito');

        $this->expectException(SellerOrgUnitInvalid::class);

        (new UpdateSeller($this->sellers, $this->orgUnits))((int) $created->seller->id(), 'Garajito', 80);
    }

    public function testUpdatingAnUnknownSellerFails(): void
    {
        $this->expectException(SellerNotFound::class);

        (new UpdateSeller($this->sellers, $this->orgUnits))(99, 'Nadie');
    }

    public function testItDeactivatesAndActivatesASeller(): void
    {
        $created = ($this->create())(7, 'Garajito');
        $toggle = new SetSellerActive($this->sellers, $this->orgUnits);

        $this->assertFalse($toggle((int) $created->seller->id(), false)->seller->isActive());
        $this->assertTrue($toggle((int) $created->seller->id(), true)->seller->isActive());
    }

    public function testThePlatformSellerCannotBeDeactivated(): void
    {
        $platform = $this->platform();

        try {
            (new SetSellerActive($this->sellers, $this->orgUnits))((int) $platform->id(), false);
            $this->fail('Debió proteger al vendedor de plataforma');
        } catch (PlatformSellerProtected) {
            $this->assertTrue($this->sellers->findPlatform()?->isActive());
        }
    }

    public function testItGetsASellerWithItsUnitAndCostCenterOrFailsWhenUnknown(): void
    {
        $created = ($this->create())(7, 'Garajito');
        $get = new GetSeller($this->sellers, $this->orgUnits);

        $this->assertSame('Garajito Foods SL', $get((int) $created->seller->id())->orgUnit?->name);

        $this->expectException(SellerNotFound::class);
        $get(99);
    }

    public function testItListsSellersFilteredByTextAndStateOrderedByName(): void
    {
        ($this->create())(7, 'Garajito');
        $other = ($this->create())(8, 'La Jabalina');
        (new SetSellerActive($this->sellers, $this->orgUnits))((int) $other->seller->id(), false);
        $list = new ListSellers($this->sellers, $this->orgUnits);

        $this->assertSame(['Garajito', 'La Jabalina'], array_map(static fn ($d) => $d->seller->name(), $list(null, null)));
        $this->assertSame(['Garajito'], array_map(static fn ($d) => $d->seller->name(), $list(null, true)));
        $this->assertSame(['La Jabalina'], array_map(static fn ($d) => $d->seller->name(), $list('jabal', null)));
    }

    public function testItGivesTheSingleSellerOfThePlatformForProductsToUseByDefault(): void
    {
        $platform = $this->platform();

        $found = (new GetPlatformSeller($this->sellers))();

        $this->assertSame($platform->id(), $found->id());
        $this->assertTrue($found->isPlatform());
    }

    public function testAskingForThePlatformSellerBeforeItExistsFails(): void
    {
        $this->expectException(SellerNotFound::class);

        (new GetPlatformSeller($this->sellers))();
    }

    public function testADerivedSlugThatIsTakenGetsTheNextNumber(): void
    {
        $this->orgUnits->add(10, 'Otra empresa SL');
        $first = ($this->create())(7, 'Garajito');
        $second = ($this->create())(10, 'Garajito');

        $this->assertSame('garajito', $first->seller->slug()->value());
        $this->assertSame('garajito-2', $second->seller->slug()->value());
    }
}
