<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\SkuSuggestion;
use PHPUnit\Framework\TestCase;

/**
 * El sku sugerido al crear un producto: CATEGORÍA-NOMBRE-NNN en mayúsculas (COMR-277).
 *
 * @covers \App\Market\Catalogo\Domain\SkuSuggestion
 */
final class SkuSuggestionTest extends TestCase
{
    public function testItBuildsItFromTheCategoryTheProductNameAndAConsecutive(): void
    {
        $this->assertSame('ALI-ARROZ-001', SkuSuggestion::of('Alimentos', 'Arroz blanco grano largo', [])->value());
    }

    public function testItIgnoresAccentsCaseAndSymbols(): void
    {
        $this->assertSame('BEB-CAFE-001', SkuSuggestion::of('bebidas y refrescos', '  ¡Café con leche!  ', [])->value());
    }

    public function testItSkipsArticlesAndConnectorsAtTheStartOfTheName(): void
    {
        $this->assertSame('ALI-ARROZ-001', SkuSuggestion::of('Alimentos', 'El arroz de la casa', [])->value());
        $this->assertSame('ASE-PASTA-001', SkuSuggestion::of('Aseo', 'Pasta de dientes', [])->value());
    }

    public function testTheConsecutiveContinuesFromTheHighestOneOfTheSamePrefix(): void
    {
        $existing = ['ALI-ARROZ-001', 'ALI-ARROZ-002', 'ALI-ARROZ-010', 'ALI-FRIJOL-005', 'ALI-ARROZ-INTEGRAL-007', 'OTRO'];

        $this->assertSame('ALI-ARROZ-011', SkuSuggestion::of('Alimentos', 'Arroz blanco', $existing)->value());
        $this->assertSame('ALI-FRIJOL-006', SkuSuggestion::of('Alimentos', 'Frijol negro', $existing)->value());
    }

    public function testTheConsecutiveCanGrowBeyondThreeDigits(): void
    {
        $this->assertSame('ALI-ARROZ-1000', SkuSuggestion::of('Alimentos', 'Arroz', ['ALI-ARROZ-999'])->value());
    }

    public function testAShortCategoryKeepsItsLettersAndNoCategoryUsesAGenericPrefix(): void
    {
        $this->assertSame('TV-SMART-001', SkuSuggestion::of('TV', 'Smart 55', [])->value());
        $this->assertSame('GEN-ARROZ-001', SkuSuggestion::of('', 'Arroz', [])->value());
    }

    public function testTheKeywordIsLimitedToTwelveCharacters(): void
    {
        $this->assertSame('ALI-ELECTROENCEF-001', SkuSuggestion::of('Alimentos', 'Electroencefalografo', [])->value());
    }

    public function testTheKeywordKeepsDigitsAndLetters(): void
    {
        $this->assertSame('BEB-7UP-001', SkuSuggestion::of('Bebidas', '7Up lata', [])->value());
    }

    /**
     * @dataProvider namesWithoutAKeyword
     */
    public function testAnEmptyNameCannotBeSuggested(string $name): void
    {
        $this->expectException(InvalidProduct::class);

        SkuSuggestion::of('Alimentos', $name, []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesWithoutAKeyword(): iterable
    {
        yield 'vacío' => [''];
        yield 'espacios' => ['   '];
        yield 'solo símbolos' => ['¡¿!?'];
        yield 'solo conectores' => ['de la'];
    }
}
