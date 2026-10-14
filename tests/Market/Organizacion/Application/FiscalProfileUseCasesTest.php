<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Application\CreateFiscalProfile;
use App\Market\Organizacion\Application\FiscalFieldInput;
use App\Market\Organizacion\Application\GetFiscalProfile;
use App\Market\Organizacion\Application\ListFiscalProfiles;
use App\Market\Organizacion\Application\UpdateFiscalProfile;
use App\Market\Organizacion\Application\ValidateFiscalData;
use App\Market\Organizacion\Domain\FiscalFieldCannotBeRemoved;
use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileAlreadyExists;
use App\Market\Organizacion\Domain\FiscalProfileNotFound;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Application\CreateFiscalProfile
 * @covers \App\Market\Organizacion\Application\UpdateFiscalProfile
 * @covers \App\Market\Organizacion\Application\GetFiscalProfile
 * @covers \App\Market\Organizacion\Application\ListFiscalProfiles
 * @covers \App\Market\Organizacion\Application\ValidateFiscalData
 */
final class FiscalProfileUseCasesTest extends TestCase
{
    private InMemoryFiscalProfileRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryFiscalProfileRepository();
    }

    /**
     * @return list<FiscalFieldInput>
     */
    private function esFields(): array
    {
        return [
            new FiscalFieldInput('nif', 'NIF o CIF', 'text', required: true, pattern: '^[A-Za-z0-9]{9}$', position: 1),
            new FiscalFieldInput('iva_intracomunitario', 'IVA intracomunitario', 'text', position: 2),
        ];
    }

    public function testItCreatesAProfileForACountry(): void
    {
        $profile = (new CreateFiscalProfile($this->repository))('ES', 'España', $this->esFields());

        $this->assertSame('ES', $profile->countryCode());
        $this->assertCount(2, $profile->fields());
        $this->assertSame($profile, $this->repository->findByCountry('ES'));
    }

    public function testItDoesNotCreateTheSameCountryTwice(): void
    {
        $create = new CreateFiscalProfile($this->repository);
        $create('ES', 'España', $this->esFields());

        $this->expectException(FiscalProfileAlreadyExists::class);
        $create('ES', 'España otra vez', []);
    }

    public function testAnInvalidFieldIsRejectedAndNothingIsSaved(): void
    {
        try {
            (new CreateFiscalProfile($this->repository))('ES', 'España', [new FiscalFieldInput('NIF', 'NIF', 'text')]);
            $this->fail('Debía lanzar InvalidFiscalProfile');
        } catch (InvalidFiscalProfile) {
            $this->assertSame(0, $this->repository->saves);
        }
    }

    public function testAnUnknownFieldTypeIsRejected(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        (new CreateFiscalProfile($this->repository))('ES', 'España', [new FiscalFieldInput('nif', 'NIF', 'telefono')]);
    }

    public function testItUpdatesTheNameAndTheFields(): void
    {
        (new CreateFiscalProfile($this->repository))('ES', 'España', $this->esFields());

        $updated = (new UpdateFiscalProfile($this->repository))('ES', 'Reino de España', [
            new FiscalFieldInput('nif', 'NIF o CIF', 'text', required: true, position: 1),
            new FiscalFieldInput('iva_intracomunitario', 'IVA intracomunitario', 'text', active: false, position: 2),
            new FiscalFieldInput('telefono', 'Teléfono', 'text', position: 3),
        ]);

        $this->assertSame('Reino de España', $updated->name());
        $this->assertCount(3, $updated->fields());
        $this->assertFalse($updated->fields()[1]->active);
    }

    public function testAFieldCannotBeRemovedAndTheNameStaysUntouched(): void
    {
        (new CreateFiscalProfile($this->repository))('ES', 'España', $this->esFields());

        try {
            (new UpdateFiscalProfile($this->repository))('ES', 'Otro nombre', [new FiscalFieldInput('nif', 'NIF', 'text', position: 1)]);
            $this->fail('Debía lanzar FiscalFieldCannotBeRemoved');
        } catch (FiscalFieldCannotBeRemoved) {
            $this->assertSame('España', $this->repository->findByCountry('ES')?->name());
        }
    }

    public function testUpdatingAnUnknownCountryFails(): void
    {
        $this->expectException(FiscalProfileNotFound::class);

        (new UpdateFiscalProfile($this->repository))('FR', 'Francia', []);
    }

    public function testItGetsAndListsProfiles(): void
    {
        $create = new CreateFiscalProfile($this->repository);
        $create('US', 'Estados Unidos', []);
        $create('ES', 'España', []);
        $this->repository->save(FiscalProfile::generic());

        $this->assertSame('ES', (new GetFiscalProfile($this->repository))('ES')->countryCode());
        $this->assertSame(['ES', 'US', 'ZZ'], array_map(
            static fn (FiscalProfile $p) => $p->countryCode(),
            (new ListFiscalProfiles($this->repository))(),
        ));

        $this->expectException(FiscalProfileNotFound::class);
        (new GetFiscalProfile($this->repository))('FR');
    }

    public function testItValidatesAgainstTheProfileOfTheCountry(): void
    {
        (new CreateFiscalProfile($this->repository))('ES', 'España', $this->esFields());
        $validate = new ValidateFiscalData($this->repository);

        $this->assertSame([], $validate('ES', ['nif' => 'B12345678']));
        $this->assertSame(['nif' => 'required'], $validate('ES', []));
        $this->assertSame(['nif' => 'format'], $validate('ES', ['nif' => 'corto']));
    }

    public function testACountryWithoutProfileUsesTheGenericOneAndAcceptsAnything(): void
    {
        $validate = new ValidateFiscalData($this->repository);

        $this->assertSame([], $validate('FR', ['siret' => '123']));
    }
}
