<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\Classification;
use App\Market\Catalogo\Domain\InvalidProduct;
use PHPUnit\Framework\TestCase;

/**
 * T1 y T4: el tipo de un producto lo dan el servicio, el subservicio y, si aplica, el área (como `ServiceCategoryKey`);
 * no hay un campo `kind` aparte. «Gift Cards» es el servicio, «Retail» un subservicio y «Supermarket» un área que cuelga de
 * «Retail».
 *
 * @covers \App\Market\Catalogo\Domain\Classification
 */
final class ClassificationTest extends TestCase
{
    public function testItIsTheServiceTheSubserviceAndAnOptionalArea(): void
    {
        $classification = Classification::of('Gift Cards', 'Retail', 'Supermarket');

        $this->assertSame('Gift Cards', $classification->service());
        $this->assertSame('Retail', $classification->subservice());
        $this->assertSame('Supermarket', $classification->area());
    }

    public function testTheAreaIsOptional(): void
    {
        $this->assertNull(Classification::of('Gift Cards', 'Gaming')->area());
        $this->assertNull(Classification::of('Gift Cards', 'Gaming', '  ')->area());
        $this->assertNull(Classification::of('Gift Cards', 'Gaming', '')->area());
    }

    public function testItTrimsTheNames(): void
    {
        $classification = Classification::of(' Gift Cards ', ' Retail ', ' Supermarket ');

        $this->assertSame('Gift Cards', $classification->service());
        $this->assertSame('Retail', $classification->subservice());
        $this->assertSame('Supermarket', $classification->area());
    }

    public function testTheSubservicesAreTheCategoriesOfDTOne(): void
    {
        $this->assertSame(
            ['Retail', 'Gaming', 'Cash Cards', 'Food', 'Entertainment', 'Travel & Transport'],
            Classification::SUBSERVICES,
        );
        foreach (Classification::SUBSERVICES as $subservice) {
            $this->assertSame($subservice, Classification::of('Gift Cards', $subservice)->subservice());
        }
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function invalid(): iterable
    {
        yield 'servicio desconocido' => ['Mobile', 'Retail', null];
        yield 'Retail no es un servicio' => ['Retail', 'Gaming', null];
        yield 'subservicio desconocido' => ['Gift Cards', 'Electronics', null];
        yield 'Gift Cards no es un subservicio' => ['Gift Cards', 'Gift Cards', null];
        yield 'subservicio vacío' => ['Gift Cards', '', null];
        yield 'área desconocida' => ['Gift Cards', 'Retail', 'Bakery'];
        yield 'Retail no es un área' => ['Gift Cards', 'Retail', 'Retail'];
        yield 'Gift Cards no es un área' => ['Gift Cards', 'Retail', 'Gift Cards'];
        yield 'el área de otro subservicio' => ['Gift Cards', 'Gaming', 'Supermarket'];
        yield 'las áreas distinguen mayúsculas' => ['Gift Cards', 'Retail', 'supermarket'];
    }

    /**
     * @dataProvider invalid
     */
    public function testItRejectsWhatIsNotInTheControlledVocabularyOrIsInTheWrongPlace(string $service, string $subservice, ?string $area): void
    {
        $this->expectException(InvalidProduct::class);

        Classification::of($service, $subservice, $area);
    }

    public function testTheAreaIsASubdivisionOfItsSubservice(): void
    {
        $this->assertSame(['Supermarket' => 'Retail'], Classification::AREAS);
    }
}
