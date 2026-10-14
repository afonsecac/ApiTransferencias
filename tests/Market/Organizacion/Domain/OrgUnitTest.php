<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Domain\OrgUnit
 */
final class OrgUnitTest extends TestCase
{
    /**
     * @param list<OrgUnitType>|null $types
     */
    private function unit(?array $types = null, string $country = 'ES', string $taxId = ' b12345678 '): OrgUnit
    {
        return new OrgUnit(
            name: 'Distribuidora Sol',
            legalName: 'Distribuidora Sol S.L.',
            countryCode: $country,
            taxId: $taxId,
            address: 'Calle Mayor 1, Madrid',
            billingEmail: 'facturas@sol.example',
            types: $types ?? [OrgUnitType::SELLER],
            fiscalData: ['nif' => 'B12345678'],
        );
    }

    public function testItNormalizesTheTaxIdAndKeepsTheCountry(): void
    {
        $unit = $this->unit();

        $this->assertSame('B12345678', $unit->taxId());
        $this->assertSame('ES', $unit->countryCode());
    }

    public function testItCanBeSeveralTypesAtOnce(): void
    {
        $unit = $this->unit([OrgUnitType::SELLER, OrgUnitType::BUYER, OrgUnitType::SELLER]);

        $this->assertSame([OrgUnitType::SELLER, OrgUnitType::BUYER], $unit->types());
    }

    public function testItNeedsAtLeastOneType(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->unit([]);
    }

    /**
     * @dataProvider invalidCountryProvider
     */
    public function testItRejectsAnInvalidCountry(string $country): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->unit(country: $country);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCountryProvider(): iterable
    {
        yield 'minúsculas' => ['es'];
        yield 'tres letras' => ['ESP'];
        yield 'el código reservado del genérico' => ['ZZ'];
    }

    public function testItRejectsABlankNameOrTaxIdAndAnInvalidEmail(): void
    {
        foreach ([
            fn () => new OrgUnit('  ', 'Legal', 'ES', 'B1', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], []),
            fn () => new OrgUnit('Nombre', '', 'ES', 'B1', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], []),
            fn () => new OrgUnit('Nombre', 'Legal', 'ES', '   ', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], []),
            fn () => new OrgUnit('Nombre', 'Legal', 'ES', 'B1', 'Dir', 'no-es-un-correo', [OrgUnitType::SELLER], [], []),
        ] as $build) {
            try {
                $build();
                $this->fail('Debía lanzar InvalidOrgUnit');
            } catch (InvalidOrgUnit) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- edición ----

    public function testItUpdatesItsDataButNotItsCountry(): void
    {
        $unit = $this->unit();

        $unit->update(
            name: 'Sol y Luna',
            legalName: 'Sol y Luna S.L.',
            taxId: 'b87654321',
            address: 'Otra calle 2',
            billingEmail: 'otro@sol.example',
            types: [OrgUnitType::SELLER, OrgUnitType::CARRIER],
            fiscalData: ['nif' => 'B87654321'],
        );

        $this->assertSame('Sol y Luna', $unit->name());
        $this->assertSame('B87654321', $unit->taxId());
        $this->assertSame('ES', $unit->countryCode());
        $this->assertSame([OrgUnitType::SELLER, OrgUnitType::CARRIER], $unit->types());
        $this->assertSame(['nif' => 'B87654321'], $unit->fiscalData());
    }

    public function testAFailedUpdateLeavesTheUnitUntouched(): void
    {
        $unit = $this->unit();

        try {
            $unit->update('Nuevo', 'Legal', 'B1', 'Dir', 'correo-malo', [OrgUnitType::SELLER], []);
            $this->fail('Debía lanzar InvalidOrgUnit');
        } catch (InvalidOrgUnit) {
            $this->assertSame('Distribuidora Sol', $unit->name());
        }
    }
}
