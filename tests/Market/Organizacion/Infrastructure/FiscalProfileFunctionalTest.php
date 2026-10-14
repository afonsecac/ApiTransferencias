<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Infrastructure;

use App\Market\Organizacion\Application\ValidateFiscalData;
use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileRepository;
use App\Market\Organizacion\Infrastructure\Http\CreateFiscalProfileDto;
use App\Market\Organizacion\Infrastructure\Http\FiscalFieldDto;
use App\Market\Organizacion\Infrastructure\Http\FiscalProfileController;
use App\Market\Organizacion\Infrastructure\Http\UpdateFiscalProfileDto;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMR-270: registro fiscal por país contra Postgres real — persistencia, contrato HTTP y datos sembrados.
 *
 * @covers \App\Market\Organizacion\Infrastructure\Persistence\DoctrineFiscalProfileRepository
 * @covers \App\Market\Organizacion\Infrastructure\Http\FiscalProfileController
 */
final class FiscalProfileFunctionalTest extends ProviderFunctionalTestCase
{
    private function controller(): FiscalProfileController
    {
        return self::getContainer()->get(FiscalProfileController::class);
    }

    private function repository(): FiscalProfileRepository
    {
        return self::getContainer()->get(FiscalProfileRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function testTheFiveStartingCountriesAndTheGenericOneAreSeeded(): void
    {
        $codes = array_map(static fn (FiscalProfile $p) => $p->countryCode(), $this->repository()->findAll());

        foreach (['CU', 'ES', 'CN', 'PA', 'US', 'ZZ'] as $expected) {
            $this->assertContains($expected, $codes);
        }
        // El genérico, el último.
        $this->assertSame('ZZ', end($codes));
    }

    public function testTheSeededUnitedStatesProfileValidatesLikeTheProposal(): void
    {
        $validate = new ValidateFiscalData($this->repository());

        $this->assertSame([], $validate('US', ['ein' => '12-3456789', 'estado' => 'NY', 'formulario_fiscal' => 'W-9']));
        $this->assertSame(['ein' => 'required', 'estado' => 'required'], $validate('US', []));
        $this->assertSame(['ein' => 'format'], $validate('US', ['ein' => '123', 'estado' => 'NY']));
        // Un país sin registro propio usa el genérico y no pide nada.
        $this->assertSame([], $validate('FR', []));
    }

    public function testAProfileSurvivesAPersistenceRoundTripWithItsFieldsInOrder(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $this->controller()->create(new CreateFiscalProfileDto('FR', 'Francia', [
            new FiscalFieldDto('tva', 'TVA', 'text', true, '^FR[0-9A-Z]{2}[0-9]{9}$', 'Número de TVA', null, true, 2),
            new FiscalFieldDto('siret', 'SIRET', 'text', true, '^[0-9]{14}$', null, null, true, 1),
        ]));
        $this->em->clear();

        $profile = $this->repository()->findByCountry('FR');

        $this->assertNotNull($profile);
        $this->assertSame(['siret', 'tva'], array_map(static fn ($f) => $f->key, $profile->fields()));
        $this->assertSame('^FR[0-9A-Z]{2}[0-9]{9}$', $profile->fields()[1]->pattern);
    }

    public function testListAnswersEverySeededProfile(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $response = $this->controller()->list();
        $data = $this->body($response);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(6, count($data));
        $this->assertSame('ZZ', end($data)['countryCode']);
        $this->assertTrue(end($data)['generic']);
    }

    public function testGetAnswersTheProfileOrNotFound(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $ok = $this->controller()->get('CN');
        $this->assertSame(Response::HTTP_OK, $ok->getStatusCode());
        $this->assertSame('uscc', $this->body($ok)['fields'][0]['key']);

        $missing = $this->controller()->get('JP');
        $this->assertSame(Response::HTTP_NOT_FOUND, $missing->getStatusCode());
        $this->assertSame('FISCAL_PROFILE_NOT_FOUND', $this->body($missing)['error']['code']);
    }

    public function testCreatingTheSameCountryTwiceAnswersConflict(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());
        $dto = new CreateFiscalProfileDto('DE', 'Alemania', []);
        $this->controller()->create($dto);

        $response = $this->controller()->create($dto);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('FISCAL_PROFILE_ALREADY_EXISTS', $this->body($response)['error']['code']);
    }

    public function testAFieldWithAnInvalidPatternAnswersUnprocessableEntity(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $response = $this->controller()->create(new CreateFiscalProfileDto('IT', 'Italia', [
            new FiscalFieldDto('piva', 'Partita IVA', 'text', true, '([0-9', null, null, true, 1),
        ]));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('INVALID_FISCAL_PROFILE', $this->body($response)['error']['code']);
    }

    public function testUpdatingDeactivatesAFieldButRemovingOneIsRejected(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $deactivate = $this->controller()->update('PA', new UpdateFiscalProfileDto('Panamá', [
            new FiscalFieldDto('ruc', 'RUC', 'text', true, null, null, null, true, 1),
            new FiscalFieldDto('dv', 'Dígito verificador', 'text', true, '^[0-9]{1,2}$', null, null, false, 2),
        ]));
        $this->assertSame(Response::HTTP_OK, $deactivate->getStatusCode());
        $this->assertFalse($this->body($deactivate)['fields'][1]['active']);

        $remove = $this->controller()->update('PA', new UpdateFiscalProfileDto('Panamá', [
            new FiscalFieldDto('ruc', 'RUC', 'text', true, null, null, null, true, 1),
        ]));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $remove->getStatusCode());
        $this->em->clear();
        $this->assertCount(2, $this->repository()->findByCountry('PA')?->fields() ?? []);
    }

    public function testUpdatingAnUnknownCountryAnswersNotFound(): void
    {
        $this->authenticateAsAdmin($this->createAdminUser());

        $response = $this->controller()->update('JP', new UpdateFiscalProfileDto('Japón', []));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTheRoutesLiveUnderTheDashboardPrefixAndNeedCredentials(): void
    {
        $router = self::getContainer()->get('router');
        $path = $router->getRouteCollection()->get('market_fiscal_profiles_list')?->getPath();
        $this->assertSame('/dashboard/api/market/fiscal-profiles', $path);

        $response = self::$kernel->handle(Request::create('/dashboard/api/market/fiscal-profiles', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
