<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Infrastructure;

use App\DTO\CreateClientDto;
use App\Entity\Client;
use App\Exception\MyCurrentException;
use App\Market\Organizacion\Application\LinkBuyerToOrgUnit;
use App\Market\Organizacion\Domain\OrgUnitRepository;
use App\Market\Organizacion\Domain\OrgUnitType;
use App\Service\ClientService;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;

/**
 * COMR-272 contra Postgres real: el comprador (Client) queda referenciado a su unidad organizativa, también
 * cuando se crea después de la migración.
 *
 * @covers \App\Market\Organizacion\Application\LinkBuyerToOrgUnit
 * @covers \App\Market\Organizacion\Infrastructure\Persistence\DoctrineBuyerRegistry
 * @covers \App\Service\ClientService
 */
final class BuyerOrgUnitFunctionalTest extends ProviderFunctionalTestCase
{
    private function dto(string $country = 'MLT', string $identification = 'C63841'): CreateClientDto
    {
        return new CreateClientDto(
            companyName: 'AWS Malta Ltd',
            companyAddress: 'St Julians, Malta',
            companyCountry: $country,
            companyEmail: 'ops@aws-malta.example',
            companyPhoneNumber: '+35600000000',
            discountOfClient: 0.0,
            companyIdentification: $identification,
            companyIdentificationType: 'CIF',
        );
    }

    private function clientService(): ClientService
    {
        return self::getContainer()->get(ClientService::class);
    }

    public function testANewClientGetsItsBuyerUnitAndPrincipalCostCenter(): void
    {
        $environment = $this->createEnvironment();

        $client = $this->clientService()->create($this->dto(), (int) $environment->getId());

        $unit = $client->getOrgUnit();
        $this->assertNotNull($unit);
        $this->assertSame([OrgUnitType::BUYER], $unit->types());
        $this->assertSame('MT', $unit->countryCode());
        $this->assertSame('C63841', $unit->taxId());
        $centers = self::getContainer()->get(OrgUnitRepository::class)->costCentersOf($unit);
        $this->assertCount(1, $centers);
        $this->assertTrue($centers[0]->isPrincipal());
        $this->assertSame('AWS Malta Ltd', $centers[0]->name());
    }

    public function testTheLinkSurvivesAReload(): void
    {
        $environment = $this->createEnvironment();
        $client = $this->clientService()->create($this->dto(), (int) $environment->getId());
        $unitId = $client->getOrgUnit()?->id();

        $this->em->clear();
        $reloaded = $this->em->find(Client::class, $client->getId());

        $this->assertSame($unitId, $reloaded?->getOrgUnit()?->id());
    }

    public function testACountryItCannotConvertRejectsTheClientAndCreatesNothing(): void
    {
        $environment = $this->createEnvironment();
        $clients = $this->em->getRepository(Client::class)->count([]);

        try {
            $this->clientService()->create($this->dto(country: 'STJ'), (int) $environment->getId());
            $this->fail('Debió rechazar el país.');
        } catch (MyCurrentException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertStringContainsString('STJ', $e->getMessage());
        }

        $this->assertSame($clients, $this->em->getRepository(Client::class)->count([]));
    }

    public function testTheUseCaseIsIdempotentOverTheRealRegistry(): void
    {
        $client = $this->createClient();
        $link = self::getContainer()->get(LinkBuyerToOrgUnit::class);

        $first = $link((int) $client->getId());
        $second = $link((int) $client->getId());

        $this->assertSame($first->unit->id(), $second->unit->id());
        $this->assertSame('CU', $first->unit->countryCode());
    }
}
