<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Domain\OrgUnit
 * @covers \App\Market\Organizacion\Domain\CostCenter
 */
final class OrgUnitTest extends TestCase
{
    /**
     * @param list<OrgUnitType>|null $types
     * @param list<CostCenter>|null $costCenters
     */
    private function unit(?array $types = null, ?array $costCenters = null, string $country = 'ES', string $taxId = ' b12345678 '): OrgUnit
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
            costCenters: $costCenters ?? [new CostCenter('GEN', 'General', true)],
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
            fn () => new OrgUnit('  ', 'Legal', 'ES', 'B1', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], [new CostCenter('GEN', 'General', true)]),
            fn () => new OrgUnit('Nombre', '', 'ES', 'B1', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], [new CostCenter('GEN', 'General', true)]),
            fn () => new OrgUnit('Nombre', 'Legal', 'ES', '   ', 'Dir', 'a@b.example', [OrgUnitType::SELLER], [], [new CostCenter('GEN', 'General', true)]),
            fn () => new OrgUnit('Nombre', 'Legal', 'ES', 'B1', 'Dir', 'no-es-un-correo', [OrgUnitType::SELLER], [], [new CostCenter('GEN', 'General', true)]),
        ] as $build) {
            try {
                $build();
                $this->fail('Debía lanzar InvalidOrgUnit');
            } catch (InvalidOrgUnit) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- centros de costo ----

    public function testItNeedsAtLeastOneCostCenter(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->unit(costCenters: []);
    }

    public function testItNeedsExactlyOnePrincipalCostCenterFromTheStart(): void
    {
        foreach ([
            [new CostCenter('A', 'A', false)],
            [new CostCenter('A', 'A', true), new CostCenter('B', 'B', true)],
        ] as $centers) {
            try {
                $this->unit(costCenters: $centers);
                $this->fail('Debía lanzar InvalidOrgUnit');
            } catch (InvalidOrgUnit) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testItRejectsRepeatedCostCenterCodes(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->unit(costCenters: [new CostCenter('A', 'A', true), new CostCenter('a', 'Otra A', false)]);
    }

    public function testACostCenterCodeIsNormalizedAndValidated(): void
    {
        $this->assertSame('CC-01', (new CostCenter(' cc-01 ', 'Uno', true))->code);

        $this->expectException(InvalidOrgUnit::class);
        new CostCenter('con espacios', 'Uno', true);
    }

    public function testItAddsAnotherCostCenterThatIsNotThePrincipal(): void
    {
        $unit = $this->unit();

        $unit->addCostCenter('almacen', 'Almacén central');

        $this->assertCount(2, $unit->costCenters());
        $this->assertSame('GEN', $unit->principalCostCenter()->code);
    }

    public function testItDoesNotAddARepeatedCostCenter(): void
    {
        $unit = $this->unit();

        $this->expectException(InvalidOrgUnit::class);
        $unit->addCostCenter('gen', 'Repetido');
    }

    public function testItChangesThePrincipalWithoutEverHavingTwoOrNone(): void
    {
        $unit = $this->unit();
        $unit->addCostCenter('ALM', 'Almacén');

        $unit->makePrincipal('ALM');

        $principals = array_filter($unit->costCenters(), static fn (CostCenter $c) => $c->principal);
        $this->assertCount(1, $principals);
        $this->assertSame('ALM', $unit->principalCostCenter()->code);
    }

    public function testThePrincipalCannotBeDeactivated(): void
    {
        $unit = $this->unit();

        $this->expectException(InvalidOrgUnit::class);
        $unit->deactivateCostCenter('GEN');
    }

    public function testASecondaryCostCenterCanBeDeactivatedAndReactivated(): void
    {
        $unit = $this->unit();
        $unit->addCostCenter('ALM', 'Almacén');

        $unit->deactivateCostCenter('ALM');
        $this->assertFalse($unit->costCenter('ALM')->active);

        $unit->activateCostCenter('ALM');
        $this->assertTrue($unit->costCenter('ALM')->active);
    }

    public function testAnInactiveCostCenterCannotBecomeThePrincipal(): void
    {
        $unit = $this->unit();
        $unit->addCostCenter('ALM', 'Almacén');
        $unit->deactivateCostCenter('ALM');

        $this->expectException(InvalidOrgUnit::class);
        $unit->makePrincipal('ALM');
    }

    public function testItRenamesACostCenterKeepingItsCode(): void
    {
        $unit = $this->unit();

        $unit->renameCostCenter('GEN', 'Central');

        $this->assertSame('Central', $unit->costCenter('GEN')->name);
        $this->assertTrue($unit->costCenter('GEN')->principal);
    }

    public function testAnUnknownCostCenterFails(): void
    {
        $unit = $this->unit();

        $this->expectException(InvalidOrgUnit::class);
        $unit->makePrincipal('NOPE');
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
