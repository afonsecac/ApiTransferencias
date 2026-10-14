<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\CountryCodes;
use App\Market\Organizacion\Domain\UnknownBuyerCountry;
use PHPUnit\Framework\TestCase;

/**
 * COMR-272: el Client guarda el país en ISO-3 y la unidad organizativa lo exige en ISO-2.
 *
 * @covers \App\Market\Organizacion\Domain\CountryCodes
 */
final class CountryCodesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownCodes(): iterable
    {
        yield 'España' => ['ESP', 'ES'];
        yield 'Cuba' => ['CUB', 'CU'];
        yield 'China' => ['CHN', 'CN'];
        yield 'Panamá' => ['PAN', 'PA'];
        yield 'Estados Unidos' => ['USA', 'US'];
        yield 'Malta' => ['MLT', 'MT'];
    }

    /**
     * @dataProvider knownCodes
     */
    public function testItConvertsIso3ToIso2(string $iso3, string $iso2): void
    {
        $this->assertSame($iso2, CountryCodes::iso2FromIso3($iso3));
    }

    public function testItConvertsIso2BackToIso3(): void
    {
        $this->assertSame('ESP', CountryCodes::iso3FromIso2('ES'));
        $this->assertSame('CUB', CountryCodes::iso3FromIso2(' cu '));
        $this->assertNull(CountryCodes::iso3FromIso2('XX'));
    }

    public function testItCoversTheSameCountriesTheDashboardOffers(): void
    {
        // Los 249 países ISO-3166 + Kosovo (XK), que es lo que ofrece el select de país del dashboard.
        $this->assertCount(250, CountryCodes::all());
        foreach (CountryCodes::all() as $iso2 => $iso3) {
            $this->assertSame($iso2, CountryCodes::iso2FromIso3($iso3));
        }
        foreach (['AW', 'KY', 'VG', 'XK', 'AX'] as $iso2) {
            $this->assertArrayHasKey($iso2, CountryCodes::all());
        }
    }

    public function testItIgnoresCaseAndSurroundingSpaces(): void
    {
        $this->assertSame('ES', CountryCodes::iso2FromIso3(' esp '));
    }

    public function testItRejectsACodeItDoesNotKnow(): void
    {
        $this->expectException(UnknownBuyerCountry::class);
        $this->expectExceptionMessage('STJ');

        CountryCodes::iso2FromIso3('STJ');
    }
}
