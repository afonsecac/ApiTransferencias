<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Application\AddCostCenter;
use App\Market\Organizacion\Application\CostCenterInput;
use App\Market\Organizacion\Application\CreateFiscalProfile;
use App\Market\Organizacion\Application\CreateOrgUnit;
use App\Market\Organizacion\Application\FiscalFieldInput;
use App\Market\Organizacion\Application\GetOrgUnit;
use App\Market\Organizacion\Application\ListOrgUnits;
use App\Market\Organizacion\Application\OrgUnitInput;
use App\Market\Organizacion\Application\SetPrincipalCostCenter;
use App\Market\Organizacion\Application\UpdateCostCenter;
use App\Market\Organizacion\Application\UpdateOrgUnit;
use App\Market\Organizacion\Application\ValidateFiscalData;
use App\Market\Organizacion\Domain\InvalidFiscalData;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitAlreadyExists;
use App\Market\Organizacion\Domain\OrgUnitNotFound;
use App\Market\Organizacion\Domain\OrgUnitType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Application\CreateOrgUnit
 * @covers \App\Market\Organizacion\Application\UpdateOrgUnit
 * @covers \App\Market\Organizacion\Application\GetOrgUnit
 * @covers \App\Market\Organizacion\Application\ListOrgUnits
 * @covers \App\Market\Organizacion\Application\AddCostCenter
 * @covers \App\Market\Organizacion\Application\UpdateCostCenter
 * @covers \App\Market\Organizacion\Application\SetPrincipalCostCenter
 */
final class OrgUnitUseCasesTest extends TestCase
{
    private InMemoryOrgUnitRepository $units;
    private CreateOrgUnit $create;

    protected function setUp(): void
    {
        $this->units = new InMemoryOrgUnitRepository();
        $profiles = new InMemoryFiscalProfileRepository();
        (new CreateFiscalProfile($profiles))('ES', 'España', [
            new FiscalFieldInput('nif', 'NIF o CIF', 'text', required: true, pattern: '^[A-Za-z0-9]{9}$', position: 1),
        ]);
        $this->create = new CreateOrgUnit($this->units, new ValidateFiscalData($profiles));
    }

    private function input(string $taxId = 'B12345678', array $fiscalData = ['nif' => 'B12345678'], string $name = 'Distribuidora Sol'): OrgUnitInput
    {
        return new OrgUnitInput(
            name: $name,
            legalName: 'Distribuidora Sol S.L.',
            taxId: $taxId,
            address: 'Calle Mayor 1, Madrid',
            billingEmail: 'facturas@sol.example',
            types: ['seller'],
            fiscalData: $fiscalData,
        );
    }

    /**
     * @return list<CostCenterInput>
     */
    private function centers(): array
    {
        return [new CostCenterInput('GEN', 'General', true)];
    }

    public function testItCreatesAUnitWithItsPrincipalCostCenter(): void
    {
        $unit = ($this->create)('ES', $this->input(), $this->centers());

        $this->assertNotNull($unit->id());
        $this->assertSame('ES', $unit->countryCode());
        $this->assertSame('GEN', $unit->principalCostCenter()->code);
    }

    public function testItValidatesTheFiscalDataAgainstTheRegistryOfTheCountry(): void
    {
        try {
            ($this->create)('ES', $this->input(fiscalData: ['nif' => 'corto']), $this->centers());
            $this->fail('Debía lanzar InvalidFiscalData');
        } catch (InvalidFiscalData $e) {
            $this->assertSame(['nif' => 'format'], $e->errors());
            $this->assertSame(0, $this->units->saves);
        }
    }

    public function testAMissingRequiredFiscalFieldIsReported(): void
    {
        try {
            ($this->create)('ES', $this->input(fiscalData: []), $this->centers());
            $this->fail('Debía lanzar InvalidFiscalData');
        } catch (InvalidFiscalData $e) {
            $this->assertSame(['nif' => 'required'], $e->errors());
        }
    }

    public function testACountryWithoutRegistryUsesTheGenericOneAndAcceptsAnyData(): void
    {
        $unit = ($this->create)('FR', $this->input(fiscalData: ['siret' => '123']), $this->centers());

        $this->assertSame('FR', $unit->countryCode());
    }

    public function testItDoesNotCreateTheSameTaxIdTwiceInACountry(): void
    {
        ($this->create)('ES', $this->input(), $this->centers());

        $this->expectException(OrgUnitAlreadyExists::class);
        ($this->create)('ES', $this->input(taxId: ' b12345678 ', name: 'Otra'), $this->centers());
    }

    public function testTheSameTaxIdIsFineInAnotherCountry(): void
    {
        ($this->create)('ES', $this->input(), $this->centers());

        $other = ($this->create)('FR', $this->input(), $this->centers());

        $this->assertSame('FR', $other->countryCode());
    }

    public function testAnInvalidUnitIsNotSaved(): void
    {
        try {
            ($this->create)('ES', $this->input(), []);
            $this->fail('Debía lanzar InvalidOrgUnit');
        } catch (InvalidOrgUnit) {
            $this->assertSame(0, $this->units->saves);
        }
    }

    public function testItUpdatesAUnitAndRevalidatesItsFiscalData(): void
    {
        $unit = ($this->create)('ES', $this->input(), $this->centers());
        $update = new UpdateOrgUnit($this->units, new ValidateFiscalData(new InMemoryFiscalProfileRepository()));
        // El genérico acepta cualquier dato: aquí solo se comprueba que se guarda lo nuevo.
        $updated = $update($unit->id(), $this->input(taxId: 'B87654321', fiscalData: ['x' => 1], name: 'Sol y Luna'));

        $this->assertSame('Sol y Luna', $updated->name());
        $this->assertSame('B87654321', $updated->taxId());
    }

    public function testUpdatingKeepsTheFiscalValidationOfTheCountry(): void
    {
        $profiles = new InMemoryFiscalProfileRepository();
        (new CreateFiscalProfile($profiles))('ES', 'España', [new FiscalFieldInput('nif', 'NIF', 'text', required: true, pattern: '^[A-Za-z0-9]{9}$')]);
        $unit = ($this->create)('ES', $this->input(), $this->centers());

        $this->expectException(InvalidFiscalData::class);
        (new UpdateOrgUnit($this->units, new ValidateFiscalData($profiles)))($unit->id(), $this->input(fiscalData: ['nif' => 'x']));
    }

    public function testUpdatingToATaxIdOfAnotherUnitIsRejectedButKeepingItsOwnIsFine(): void
    {
        ($this->create)('ES', $this->input(), $this->centers());
        $second = ($this->create)('ES', $this->input(taxId: 'B22222222', fiscalData: ['nif' => 'B22222222'], name: 'Segunda'), $this->centers());
        $update = new UpdateOrgUnit($this->units, new ValidateFiscalData(new InMemoryFiscalProfileRepository()));

        $this->assertSame('Segunda', $update($second->id(), $this->input(taxId: 'B22222222', name: 'Segunda'))->name());

        $this->expectException(OrgUnitAlreadyExists::class);
        $update($second->id(), $this->input(taxId: 'B12345678', name: 'Segunda'));
    }

    public function testUpdatingAnUnknownUnitFails(): void
    {
        $this->expectException(OrgUnitNotFound::class);

        (new UpdateOrgUnit($this->units, new ValidateFiscalData(new InMemoryFiscalProfileRepository())))(99, $this->input());
    }

    public function testItGetsAndListsWithFilters(): void
    {
        $sol = ($this->create)('ES', $this->input(), $this->centers());
        $input = new OrgUnitInput('Transportes Luna', 'Luna S.A.', 'B33333333', 'Av. 3', 'luna@example.test', ['carrier', 'buyer'], ['nif' => 'B33333333']);
        ($this->create)('ES', $input, $this->centers());

        $this->assertSame($sol, (new GetOrgUnit($this->units))($sol->id()));
        $this->assertCount(2, (new ListOrgUnits($this->units))());
        $this->assertCount(1, (new ListOrgUnits($this->units))(OrgUnitType::CARRIER));
        $this->assertCount(1, (new ListOrgUnits($this->units))(null, 'luna'));
        $this->assertCount(0, (new ListOrgUnits($this->units))(OrgUnitType::PLATFORM));
    }

    public function testGettingAnUnknownUnitFails(): void
    {
        $this->expectException(OrgUnitNotFound::class);

        (new GetOrgUnit($this->units))(99);
    }

    public function testItAddsRenamesDeactivatesAndChangesThePrincipalOfCostCenters(): void
    {
        $unit = ($this->create)('ES', $this->input(), $this->centers());

        (new AddCostCenter($this->units))($unit->id(), 'alm', 'Almacén');
        (new UpdateCostCenter($this->units))($unit->id(), 'ALM', 'Almacén central', true);
        (new SetPrincipalCostCenter($this->units))($unit->id(), 'ALM');
        (new UpdateCostCenter($this->units))($unit->id(), 'GEN', 'General', false);

        $saved = (new GetOrgUnit($this->units))($unit->id());
        $this->assertSame('ALM', $saved->principalCostCenter()->code);
        $this->assertSame('Almacén central', $saved->costCenter('ALM')->name);
        $this->assertFalse($saved->costCenter('GEN')->active);
    }

    public function testThePrincipalCostCenterCannotBeDeactivatedThroughTheUseCase(): void
    {
        $unit = ($this->create)('ES', $this->input(), $this->centers());

        $this->expectException(InvalidOrgUnit::class);
        (new UpdateCostCenter($this->units))($unit->id(), 'GEN', 'General', false);
    }

    public function testACostCenterOfAnUnknownUnitFails(): void
    {
        $this->expectException(OrgUnitNotFound::class);

        (new AddCostCenter($this->units))(99, 'X', 'X');
    }
}
