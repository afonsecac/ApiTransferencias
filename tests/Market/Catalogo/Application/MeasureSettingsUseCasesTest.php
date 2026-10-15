<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Application\ChangeMeasureSettings;
use App\Market\Catalogo\Application\CreateProduct;
use App\Market\Catalogo\Application\GetMeasureSettings;
use App\Market\Catalogo\Application\ProductSpecInput;
use App\Market\Catalogo\Domain\Category;
use App\Market\Catalogo\Domain\InvalidMeasureSettings;
use App\Market\Catalogo\Domain\MeasureSettingsLocked;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * COMR-281: se pueden leer y cambiar las unidades base y el divisor. Cambiar las unidades base con productos que ya tienen
 * cantidades guardadas se rechaza (reinterpretaría todos sus datos); el divisor, que no toca datos guardados, se cambia siempre.
 *
 * @covers \App\Market\Catalogo\Application\GetMeasureSettings
 * @covers \App\Market\Catalogo\Application\ChangeMeasureSettings
 */
final class MeasureSettingsUseCasesTest extends TestCase
{
    private InMemoryMeasureSettings $settings;
    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        $this->settings = new InMemoryMeasureSettings();
        $this->products = new InMemoryProductRepository();
    }

    private function change(): ChangeMeasureSettings
    {
        return new ChangeMeasureSettings($this->settings, $this->settings, $this->products);
    }

    private function createPhysicalProduct(): void
    {
        $sellers = new InMemorySellerRepository();
        $sellers->save(Seller::platform(1, 10, new Slug('plataforma'), 'Plataforma'));
        $categories = new InMemoryCategoryRepository();
        $categories->save(Category::create(new Slug('alimentos'), null, 'Alimentos', null));
        (new CreateProduct($this->products, $sellers, new InMemoryBrandRepository(), $categories))('arr-001', null, new ProductSpecInput(...[
            'texts' => ['es' => ['name' => 'Arroz']],
            'service' => 'Gift Cards',
            'subservice' => 'Retail',
            'area' => null,
            'contentValue' => 1.0,
            'contentUnit' => 'kg',
            'unitWeightValue' => null,
            'unitWeightUnit' => null,
            'unitVolumeValue' => null,
            'unitVolumeUnit' => null,
            'lengthMm' => null,
            'widthMm' => null,
            'heightMm' => null,
            'deliveryKind' => 'weight',
            'deliveryUnit' => 'kg',
            'barcode' => null,
            'categoryIds' => [1],
        ]));
    }

    public function testItReadsTheCurrentSettingsAndSaysWhetherTheBaseIsLocked(): void
    {
        $free = (new GetMeasureSettings($this->settings, $this->products))();
        $this->createPhysicalProduct();
        $locked = (new GetMeasureSettings($this->settings, $this->products))();

        $this->assertSame([MeasureUnit::KG, MeasureUnit::L, 6000, false], [$free->weightBase, $free->volumeBase, $free->volumetricDivisor, $free->baseLocked]);
        $this->assertTrue($locked->baseLocked);
    }

    public function testWithoutProductsTheBaseUnitsCanChange(): void
    {
        $details = ($this->change())('g', 'ml', 5000);

        $this->assertSame([MeasureUnit::G, MeasureUnit::ML, 5000], [$details->weightBase, $details->volumeBase, $details->volumetricDivisor]);
        $this->assertSame(MeasureUnit::G, $this->settings->weightBase());
    }

    public function testWithProductsTheBaseUnitsCannotChange(): void
    {
        $this->createPhysicalProduct();

        try {
            ($this->change())('g', 'l', 6000);
            $this->fail('Debía rechazar el cambio de base.');
        } catch (MeasureSettingsLocked) {
            $this->assertSame(0, $this->settings->saves);
            $this->assertSame(MeasureUnit::KG, $this->settings->weightBase());
        }
    }

    public function testWithProductsTheDivisorCanStillChangeAndSavingTheSameBaseIsNotAChange(): void
    {
        $this->createPhysicalProduct();

        $details = ($this->change())('kg', 'l', 5000);

        $this->assertSame(5000, $details->volumetricDivisor);
        $this->assertSame(1, $this->settings->saves);
    }

    /**
     * @dataProvider invalid
     */
    public function testItRejectsAnInvalidSetting(string $weight, string $volume, int $divisor): void
    {
        $this->expectException(InvalidMeasureSettings::class);

        ($this->change())($weight, $volume, $divisor);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function invalid(): iterable
    {
        yield 'peso con una unidad de volumen' => ['l', 'l', 6000];
        yield 'volumen con una unidad de peso' => ['kg', 'kg', 6000];
        yield 'unidad que no existe' => ['stone', 'l', 6000];
        yield 'el conteo no es una base' => ['unit', 'l', 6000];
        yield 'divisor cero' => ['kg', 'l', 0];
        yield 'divisor negativo' => ['kg', 'l', -6000];
    }
}
