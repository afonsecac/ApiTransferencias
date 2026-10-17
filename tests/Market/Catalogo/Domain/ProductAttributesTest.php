<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductAttributes;
use PHPUnit\Framework\TestCase;

/**
 * COMR-284: los atributos libres de un producto (color, sabor, talla…): pares nombre-valor con texto por idioma; el español es
 * obligatorio y el inglés opcional, como en los textos del producto.
 *
 * @covers \App\Market\Catalogo\Domain\ProductAttributes
 */
final class ProductAttributesTest extends TestCase
{
    /** @return array{name: array<string, string>, value: array<string, string>} */
    private static function attr(string $nameEs, string $valueEs, ?string $nameEn = null, ?string $valueEn = null): array
    {
        return [
            'name' => array_filter(['es' => $nameEs, 'en' => $nameEn], static fn (?string $v) => $v !== null),
            'value' => array_filter(['es' => $valueEs, 'en' => $valueEn], static fn (?string $v) => $v !== null),
        ];
    }

    public function testNoAttributesIsValidAndEmpty(): void
    {
        $attributes = ProductAttributes::fromArray([]);

        $this->assertTrue($attributes->isEmpty());
        $this->assertSame([], $attributes->all());
        $this->assertTrue(ProductAttributes::empty()->isEmpty());
    }

    public function testItKeepsTheAttributesInOrderWithTheirTranslations(): void
    {
        $attributes = ProductAttributes::fromArray([self::attr('Color', 'Rojo', 'Colour', 'Red'), self::attr('Talla', 'M')]);

        $this->assertSame([
            ['name' => ['es' => 'Color', 'en' => 'Colour'], 'value' => ['es' => 'Rojo', 'en' => 'Red']],
            ['name' => ['es' => 'Talla'], 'value' => ['es' => 'M']],
        ], $attributes->all());
    }

    public function testTextIsTrimmedAndABlankEnglishTranslationIsDropped(): void
    {
        $attributes = ProductAttributes::fromArray([self::attr('  Color  ', ' Rojo ', '   ', '')]);

        $this->assertSame([['name' => ['es' => 'Color'], 'value' => ['es' => 'Rojo']]], $attributes->all());
    }

    public function testSpanishIsRequiredForTheNameAndTheValue(): void
    {
        foreach ([self::attr('', 'Rojo'), self::attr('Color', ''), self::attr('  ', 'Rojo'), ['name' => ['en' => 'Colour'], 'value' => ['es' => 'Rojo']], ['name' => ['es' => 'Color'], 'value' => ['en' => 'Red']]] as $bad) {
            try {
                ProductAttributes::fromArray([$bad]);
                $this->fail('Debió exigir nombre y valor en español: ' . json_encode($bad));
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testOnlySpanishAndEnglishAreAdmitted(): void
    {
        $this->expectException(InvalidProduct::class);

        ProductAttributes::fromArray([['name' => ['es' => 'Color', 'fr' => 'Couleur'], 'value' => ['es' => 'Rojo']]]);
    }

    public function testTheNameAndTheValueHaveALengthLimit(): void
    {
        foreach ([self::attr(str_repeat('n', ProductAttributes::NAME_MAX_LENGTH + 1), 'x'), self::attr('Color', str_repeat('v', ProductAttributes::VALUE_MAX_LENGTH + 1))] as $bad) {
            try {
                ProductAttributes::fromArray([$bad]);
                $this->fail('Debió rechazar un texto demasiado largo');
            } catch (InvalidProduct) {
                $this->addToAssertionCount(1);
            }
        }
        ProductAttributes::fromArray([self::attr(str_repeat('n', ProductAttributes::NAME_MAX_LENGTH), str_repeat('v', ProductAttributes::VALUE_MAX_LENGTH))]);
    }

    public function testThereIsALimitOfAttributesPerProduct(): void
    {
        $many = [];
        for ($i = 1; $i <= ProductAttributes::MAX_ATTRIBUTES; ++$i) {
            $many[] = self::attr("Atributo $i", 'x');
        }
        $this->assertCount(ProductAttributes::MAX_ATTRIBUTES, ProductAttributes::fromArray($many)->all());

        $this->expectException(InvalidProduct::class);
        $many[] = self::attr('Uno más', 'x');
        ProductAttributes::fromArray($many);
    }

    public function testTheSameNameCannotBeRepeatedIgnoringCaseAndAccents(): void
    {
        $this->expectException(InvalidProduct::class);

        ProductAttributes::fromArray([self::attr('Color', 'Rojo'), self::attr('  COLOR ', 'Azul')]);
    }

    public function testAccentsAreIgnoredWhenLookingForDuplicates(): void
    {
        $this->expectException(InvalidProduct::class);

        ProductAttributes::fromArray([self::attr('Presentación', 'Caja'), self::attr('Presentacion', 'Bolsa')]);
    }

    public function testItRoundTripsThroughItsStoredForm(): void
    {
        $original = ProductAttributes::fromArray([self::attr('Color', 'Rojo', 'Colour', 'Red'), self::attr('Talla', 'M')]);

        $this->assertEquals($original, ProductAttributes::fromArray($original->all()));
    }

    public function testAnItemThatIsNotAnObjectIsRejected(): void
    {
        $this->expectException(InvalidProduct::class);

        ProductAttributes::fromArray(['Color']);
    }

    public function testForALanguageItGivesThatTextAndFallsBackToSpanishFieldByField(): void
    {
        $attributes = ProductAttributes::fromArray([self::attr('Color', 'Rojo', 'Colour', 'Red'), self::attr('Talla', 'M', 'Size'), self::attr('Origen', 'España')]);

        $this->assertSame([
            ['name' => ['en' => 'Colour'], 'value' => ['en' => 'Red']],
            ['name' => ['en' => 'Size'], 'value' => ['es' => 'M']],
            ['name' => ['es' => 'Origen'], 'value' => ['es' => 'España']],
        ], $attributes->forLanguage('en'));
        $this->assertSame([
            ['name' => ['es' => 'Color'], 'value' => ['es' => 'Rojo']],
            ['name' => ['es' => 'Talla'], 'value' => ['es' => 'M']],
            ['name' => ['es' => 'Origen'], 'value' => ['es' => 'España']],
        ], $attributes->forLanguage('xx'));
        $this->assertSame($attributes->all(), $attributes->forLanguage(null));
    }
}
