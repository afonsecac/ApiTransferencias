<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\CreateBrand;
use App\Market\Catalogo\Application\GetBrand;
use App\Market\Catalogo\Application\ListBrands;
use App\Market\Catalogo\Application\SetBrandActive;
use App\Market\Catalogo\Application\UpdateBrand;
use App\Market\Catalogo\Domain\BrandAlreadyExists;
use App\Market\Catalogo\Domain\BrandNotFound;
use App\Market\Catalogo\Domain\BrandSellerInvalid;
use App\Market\Catalogo\Domain\InvalidBrand;
use App\Market\Catalogo\Domain\Seller;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Application\CreateBrand
 * @covers \App\Market\Catalogo\Application\UpdateBrand
 * @covers \App\Market\Catalogo\Application\SetBrandActive
 * @covers \App\Market\Catalogo\Application\GetBrand
 * @covers \App\Market\Catalogo\Application\ListBrands
 */
final class BrandUseCasesTest extends TestCase
{
    private InMemoryBrandRepository $brands;
    private InMemorySellerRepository $sellers;
    private Seller $platform;
    private Seller $garajito;

    protected function setUp(): void
    {
        $this->brands = new InMemoryBrandRepository();
        $this->sellers = new InMemorySellerRepository();
        $this->platform = Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma');
        $this->garajito = Seller::create(2, 20, new Slug('garajito'), 'Garajito');
        $this->sellers->save($this->platform);
        $this->sellers->save($this->garajito);
    }

    private function create(): CreateBrand
    {
        return new CreateBrand($this->brands, $this->sellers);
    }

    public function testItCreatesABrandOfASellerDerivingTheSlugFromTheName(): void
    {
        $details = ($this->create())((int) $this->garajito->id(), 'Cola Premium');

        $this->assertSame('cola-premium', $details->brand->slug()->value());
        $this->assertSame($this->garajito->id(), $details->brand->sellerId());
        $this->assertSame('Garajito', $details->seller?->name());
        $this->assertTrue($details->brand->isActive());
    }

    public function testItAcceptsAnExplicitSlug(): void
    {
        $details = ($this->create())((int) $this->garajito->id(), 'Cola Premium', 'cola-p');

        $this->assertSame('cola-p', $details->brand->slug()->value());
    }

    public function testTheSellerMustExistAndBeActive(): void
    {
        foreach ([999, (int) $this->garajito->id()] as $i => $sellerId) {
            if ($i === 1) {
                $this->garajito->deactivate();
            }
            try {
                ($this->create())($sellerId, 'Marca ' . $i);
                $this->fail('Debió rechazar el vendedor');
            } catch (BrandSellerInvalid) {
                $this->assertSame([], $this->brands->brands);
            }
        }
    }

    public function testTheSlugIsUniqueAcrossAllSellers(): void
    {
        ($this->create())((int) $this->garajito->id(), 'Cola Premium', 'cola-premium');

        $this->expectException(BrandAlreadyExists::class);

        ($this->create())((int) $this->platform->id(), 'Cola Premium', 'cola-premium');
    }

    public function testAnInvalidNameOrSlugCreatesNothing(): void
    {
        foreach ([['  ', null], ['Marca', 'Mal Slug']] as [$name, $slug]) {
            try {
                ($this->create())((int) $this->garajito->id(), $name, $slug);
                $this->fail('Debió rechazar los datos');
            } catch (InvalidBrand|\App\Shared\Domain\InvalidSlug) {
                $this->assertSame([], $this->brands->brands);
            }
        }
    }

    public function testItRenamesABrandButNeverItsSlugNorItsSeller(): void
    {
        $id = (int) ($this->create())((int) $this->garajito->id(), 'Cola Premium')->brand->id();

        $updated = (new UpdateBrand($this->brands, $this->sellers))($id, 'Cola Premium Plus');

        $this->assertSame('Cola Premium Plus', $updated->brand->name());
        $this->assertSame('cola-premium', $updated->brand->slug()->value());
        $this->assertSame($this->garajito->id(), $updated->brand->sellerId());
    }

    public function testUpdatingAnUnknownBrandFails(): void
    {
        $this->expectException(BrandNotFound::class);

        (new UpdateBrand($this->brands, $this->sellers))(99, 'Nada');
    }

    public function testItDeactivatesAndActivatesABrand(): void
    {
        $id = (int) ($this->create())((int) $this->garajito->id(), 'Cola Premium')->brand->id();
        $toggle = new SetBrandActive($this->brands, $this->sellers);

        $this->assertFalse($toggle($id, false)->brand->isActive());
        $this->assertTrue($toggle($id, true)->brand->isActive());
    }

    public function testItGetsABrandWithItsSellerOrFailsWhenUnknown(): void
    {
        $id = (int) ($this->create())((int) $this->garajito->id(), 'Cola Premium')->brand->id();
        $get = new GetBrand($this->brands, $this->sellers);

        $this->assertSame('Garajito', $get($id)->seller?->name());

        $this->expectException(BrandNotFound::class);
        $get(99);
    }

    public function testItListsBrandsFilteredByTextSellerAndState(): void
    {
        $create = $this->create();
        $create((int) $this->garajito->id(), 'Cola Premium');
        $other = $create((int) $this->platform->id(), 'Agua Pura');
        (new SetBrandActive($this->brands, $this->sellers))((int) $other->brand->id(), false);
        $list = new ListBrands($this->brands, $this->sellers);
        $names = static fn (array $details) => array_map(static fn ($d) => $d->brand->name(), $details);

        $this->assertSame(['Agua Pura', 'Cola Premium'], $names($list(null, null, null)));
        $this->assertSame(['Cola Premium'], $names($list('cola', null, null)));
        $this->assertSame(['Agua Pura'], $names($list(null, (int) $this->platform->id(), null)));
        $this->assertSame(['Cola Premium'], $names($list(null, null, true)));
    }

    public function testADerivedSlugThatIsTakenGetsTheNextNumber(): void
    {
        $first = ($this->create())((int) $this->garajito->id(), 'Cola Premium');
        $second = ($this->create())((int) $this->garajito->id(), 'Cola Premium');
        $third = ($this->create())((int) $this->platform->id(), 'cola   premium');

        $this->assertSame('cola-premium', $first->brand->slug()->value());
        $this->assertSame('cola-premium-2', $second->brand->slug()->value());
        $this->assertSame('cola-premium-3', $third->brand->slug()->value());
    }

    public function testAnExplicitSlugThatIsTakenIsStillRejected(): void
    {
        ($this->create())((int) $this->garajito->id(), 'Cola Premium', 'cola');

        $this->expectException(\App\Market\Catalogo\Domain\BrandAlreadyExists::class);
        ($this->create())((int) $this->garajito->id(), 'Otra marca', 'cola');
    }
}
