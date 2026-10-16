<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Brand;
use App\Market\Catalogo\Domain\InvalidBrand;
use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-70 / COMR-171: la marca es una entidad separada y pertenece a un vendedor. Una marca no se guarda sin vendedor.
 *
 * @covers \App\Market\Catalogo\Domain\Brand
 */
final class BrandTest extends TestCase
{
    private function brand(string $name = 'Cola Premium', string $slug = 'cola-premium'): Brand
    {
        return Brand::create(3, new Slug($slug), $name);
    }

    public function testItIsCreatedActiveForItsSeller(): void
    {
        $brand = $this->brand('  Cola Premium  ');

        $this->assertSame('Cola Premium', $brand->name());
        $this->assertSame('cola-premium', $brand->slug()->value());
        $this->assertSame(3, $brand->sellerId());
        $this->assertTrue($brand->isActive());
    }

    public function testItNeedsAName(): void
    {
        $this->expectException(InvalidBrand::class);

        $this->brand('   ');
    }

    public function testTheNameHasALimit(): void
    {
        $this->expectException(InvalidBrand::class);

        $this->brand(str_repeat('N', 161));
    }

    public function testItNeedsAValidSeller(): void
    {
        $this->expectException(InvalidBrand::class);

        Brand::create(0, new Slug('x'), 'Marca');
    }

    public function testItCanBeRenamedAndKeepsItsSlugAndSeller(): void
    {
        $brand = $this->brand();

        $brand->rename('Cola Premium Plus');

        $this->assertSame('Cola Premium Plus', $brand->name());
        $this->assertSame('cola-premium', $brand->slug()->value());
        $this->assertSame(3, $brand->sellerId());
        $this->assertFalse(method_exists($brand, 'changeSeller'));
    }

    public function testItIsDeactivatedAndActivatedAgain(): void
    {
        $brand = $this->brand();

        $brand->deactivate();
        $this->assertFalse($brand->isActive());

        $brand->activate();
        $this->assertTrue($brand->isActive());
    }
}
