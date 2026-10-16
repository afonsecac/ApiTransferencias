<?php

declare(strict_types=1);

namespace App\Tests\Market\Geografia\Application;

use App\Market\Geografia\Application\FindMunicipality;
use App\Market\Geografia\Application\ListProvinces;
use App\Market\Geografia\Application\SearchMunicipalities;
use App\Market\Geografia\Application\SetMunicipalityActive;
use App\Market\Geografia\Application\SetProvinceActive;
use App\Market\Geografia\Domain\GeographyNotFound;
use App\Market\Geografia\Domain\Municipality;
use App\Market\Geografia\Domain\Province;
use PHPUnit\Framework\TestCase;

final class GeographyUseCasesTest extends TestCase
{
    private InMemoryGeographyRepository $repo;
    private Province $habana;
    private Province $matanzas;

    protected function setUp(): void
    {
        $this->repo = new InMemoryGeographyRepository();
        $this->habana = $this->repo->addProvince(Province::create('la-habana', 'La Habana'));
        $this->matanzas = $this->repo->addProvince(Province::create('matanzas', 'Matanzas'));
        $this->repo->addMunicipality(Municipality::create((int) $this->habana->id(), 'boyeros', 'Boyeros'));
        $this->repo->addMunicipality(Municipality::create((int) $this->habana->id(), 'playa', 'Playa'));
        $this->repo->addMunicipality(Municipality::create((int) $this->matanzas->id(), 'cardenas', 'Cárdenas'));
    }

    public function testItListsProvincesWithTheirMunicipalityCount(): void
    {
        $list = (new ListProvinces($this->repo))();

        $this->assertSame(['La Habana' => 2, 'Matanzas' => 1], array_column(array_map(
            static fn ($d) => ['n' => $d->province->name(), 'c' => $d->municipalityCount],
            $list,
        ), 'c', 'n'));
    }

    public function testItSearchesMunicipalitiesIgnoringAccents(): void
    {
        $found = (new SearchMunicipalities($this->repo))('cardenas', null, null);

        $this->assertCount(1, $found);
        $this->assertSame('Cárdenas', $found[0]->municipality->name());
        $this->assertSame('Matanzas', $found[0]->province->name());
    }

    public function testItFiltersByProvince(): void
    {
        $this->assertCount(2, (new SearchMunicipalities($this->repo))(null, (int) $this->habana->id(), null));
    }

    public function testAnInactiveProvinceHidesItsMunicipalitiesWithoutChangingThem(): void
    {
        (new SetProvinceActive($this->repo))((int) $this->habana->id(), false);

        $selectable = (new SearchMunicipalities($this->repo))(null, null, true);
        $this->assertSame(['Cárdenas'], array_map(static fn ($d) => $d->municipality->name(), $selectable));

        $all = (new SearchMunicipalities($this->repo))('boyeros', null, null);
        $this->assertTrue($all[0]->municipality->isActive());
        $this->assertFalse($all[0]->selectable);
    }

    public function testItDeactivatesAndReactivatesAMunicipality(): void
    {
        $id = (int) $this->repo->municipalities('boyeros', null, null)[0]->id();

        $detail = (new SetMunicipalityActive($this->repo))($id, false);
        $this->assertFalse($detail->municipality->isActive());
        $this->assertFalse($detail->selectable);

        $this->assertTrue((new SetMunicipalityActive($this->repo))($id, true)->selectable);
    }

    public function testItFailsWhenTheMunicipalityOrProvinceDoesNotExist(): void
    {
        $this->expectException(GeographyNotFound::class);
        (new SetMunicipalityActive($this->repo))(999, false);
    }

    public function testItFailsWhenTheProvinceDoesNotExist(): void
    {
        $this->expectException(GeographyNotFound::class);
        (new SetProvinceActive($this->repo))(999, false);
    }

    public function testItFindsAMunicipalityByIdAndByCode(): void
    {
        $id = (int) $this->repo->municipalities('boyeros', null, null)[0]->id();
        $find = new FindMunicipality($this->repo);

        $this->assertSame('Boyeros', $find->byId($id)?->municipality->name());
        $this->assertSame('La Habana', $find->byCode('boyeros')?->province->name());
        $this->assertTrue($find->byCode('boyeros')?->selectable);
        $this->assertNull($find->byId(999));
        $this->assertNull($find->byCode('nada'));
    }
}
