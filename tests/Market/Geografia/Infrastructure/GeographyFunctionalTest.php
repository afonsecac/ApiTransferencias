<?php

declare(strict_types=1);

namespace App\Tests\Market\Geografia\Infrastructure;

use App\Market\Geografia\Infrastructure\Http\GeographyController;
use App\Market\Geografia\Infrastructure\Http\SetActiveDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-286 contra Postgres real: el catálogo sembrado por la migración y el contrato HTTP.
 *
 * @covers \App\Market\Geografia\Infrastructure\Persistence\DoctrineGeographyRepository
 * @covers \App\Market\Geografia\Infrastructure\Http\GeographyController
 */
final class GeographyFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): GeographyController
    {
        return self::getContainer()->get(GeographyController::class);
    }

    /** @return array<mixed> */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function search(string $query): array
    {
        return $this->body($this->controller()->municipalities(new Request(query: $query === '' ? [] : ['q' => $query])));
    }

    public function testTheSeedHas16FirstLevelDivisionsAnd168Municipalities(): void
    {
        $provinces = $this->body($this->controller()->provinces());

        $this->assertCount(16, $provinces);
        $this->assertSame(168, array_sum(array_column($provinces, 'municipalityCount')));
        $special = array_values(array_filter($provinces, static fn ($p) => $p['special']));
        $this->assertCount(1, $special);
        $this->assertSame('Isla de la Juventud', $special[0]['name']);
        $this->assertSame(1, $special[0]['municipalityCount']);
    }

    public function testItSearchesIgnoringAccents(): void
    {
        $found = $this->search('guines');

        $this->assertSame(['Güines', 'Quemado de Güines'], array_column($found, 'name'));
        $this->assertSame('Mayabeque', $found[0]['province']['name']);
        $this->assertTrue($found[0]['selectable']);
    }

    public function testHabanaDelEsteUsesItsCanonicalName(): void
    {
        $this->assertSame(['La Habana del Este'], array_column($this->search('habana del este'), 'name'));
    }

    public function testDeactivatingAMunicipalityRemovesItFromTheSelectableOnes(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $id = $this->search('boyeros')[0]['id'];

        $response = $this->controller()->setMunicipalityActive($id, new SetActiveDto(false));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($this->body($response)['selectable']);
        $selectable = $this->body($this->controller()->municipalities(new Request(query: ['q' => 'boyeros', 'selectable' => 'true'])));
        $this->assertSame([], $selectable);
    }

    public function testDeactivatingAProvinceHidesItsMunicipalitiesButKeepsTheirState(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $matanzas = array_values(array_filter($this->body($this->controller()->provinces()), static fn ($p) => $p['name'] === 'Matanzas'))[0];

        $this->controller()->setProvinceActive($matanzas['id'], new SetActiveDto(false));

        $found = $this->search('cardenas')[0];
        $this->assertTrue($found['active']);
        $this->assertFalse($found['selectable']);
    }

    public function testItAnswers404ForAnUnknownMunicipality(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $response = $this->controller()->setMunicipalityActive(999999, new SetActiveDto(true));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('GEOGRAPHY_NOT_FOUND', $this->body($response)['error']['code']);
    }
}
