<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\CommunicationPackage;
use App\Repository\ClientProviderRoutingRepository;
use App\Service\Catalog\ClientServiceProviderCoverageResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\ClientServiceProviderCoverageResolver
 */
class ClientServiceProviderCoverageResolverTest extends TestCase
{
    private ClientProviderRoutingRepository&MockObject $routingRepo;
    private ClientServiceProviderCoverageResolver $resolver;

    protected function setUp(): void
    {
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->resolver = new ClientServiceProviderCoverageResolver($this->routingRepo);
    }

    private function packageFor(string $serviceName, ?string $subserviceName = null): CommunicationPackage
    {
        $service = ['name' => $serviceName];
        if ($subserviceName !== null) {
            $service['subservice'] = ['name' => $subserviceName];
        }

        return (new CommunicationPackage())
            ->setName('P')
            ->setDescription('P')
            ->setDestinationAmount(1.0)
            ->setDestinationCurrency('CUP')
            ->setService($service);
    }

    private function accountFor(?int $clientId): Account&MockObject
    {
        $account = $this->createMock(Account::class);
        if ($clientId === null) {
            $account->method('getClient')->willReturn(null);

            return $account;
        }

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);
        $account->method('getClient')->willReturn($client);

        return $account;
    }

    public function testIsCoveredWhenAccountHasNoClient(): void
    {
        $this->routingRepo->expects($this->never())->method('findActiveRouteScopesForClient');

        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(null), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredWhenClientHasNoRoutingRowsAtAll(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->with(1)->willReturn([]);

        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsNotCoveredWhenClientHasRoutingForAnotherServiceOnly(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Utilities', 'subserviceName' => null, 'priority' => 100],
        ]);

        $this->assertFalse($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredWhenRowMatchesServiceWithWildcardSubservice(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Mobile', 'subserviceName' => null, 'priority' => 100],
        ]);

        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Data')));
    }

    public function testIsNotCoveredWhenRowMatchesServiceButOnlyADifferentSubservice(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Mobile', 'subserviceName' => 'Data', 'priority' => 100],
        ]);

        $this->assertFalse($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredWhenRowMatchesExactServiceAndSubservice(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Mobile', 'subserviceName' => 'Recharge', 'priority' => 100],
        ]);

        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredWhenClientHasAFullyWildcardRow(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => null, 'subserviceName' => null, 'priority' => 100],
        ]);

        $this->assertTrue($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsNotCoveredWhenPackageHasNoSubserviceAndOnlyASubserviceSpecificRowExistsForItsService(): void
    {
        $this->routingRepo->method('findActiveRouteScopesForClient')->willReturn([
            ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Mobile', 'subserviceName' => 'Data', 'priority' => 100],
        ]);

        $this->assertFalse($this->resolver->isCoveredFor($this->accountFor(1), $this->packageFor('Mobile')));
    }

    // ---- isCoveredByRows() — lógica pura reutilizada por ClientCatalogVisibilityImpactResolver para simular escenarios sin tocar el repositorio ----

    public function testIsCoveredByRowsReturnsTrueWithoutAnyRows(): void
    {
        $this->assertTrue($this->resolver->isCoveredByRows([], $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredByRowsReturnsFalseWhenNoRowMatchesTheService(): void
    {
        $rows = [['serviceName' => 'Utilities', 'subserviceName' => null]];

        $this->assertFalse($this->resolver->isCoveredByRows($rows, $this->packageFor('Mobile', 'Recharge')));
    }

    public function testIsCoveredByRowsReturnsTrueWithAWildcardSubserviceRow(): void
    {
        $rows = [['serviceName' => 'Mobile', 'subserviceName' => null]];

        $this->assertTrue($this->resolver->isCoveredByRows($rows, $this->packageFor('Mobile', 'Data')));
    }

    // ---- COMR-264 (C1): área opcional ----

    private function packageWithArea(string $area): CommunicationPackage
    {
        return (new CommunicationPackage())
            ->setName('P')
            ->setDescription('P')
            ->setDestinationAmount(1.0)
            ->setDestinationCurrency('CUP')
            ->setService(['name' => 'Gift', 'subservice' => ['name' => 'Card'], 'area' => ['name' => $area]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(?string $areaName, bool $withAreaKey = true): array
    {
        $row = ['id' => 1, 'provider' => 'ETECSA', 'fallbackProvider' => null, 'environmentId' => null, 'saleType' => null, 'serviceName' => 'Gift', 'subserviceName' => 'Card', 'priority' => 100];
        if ($withAreaKey) {
            $row['areaName'] = $areaName;
        }

        return $row;
    }

    public function testARowWithAnAreaCoversOnlyPackagesOfThatArea(): void
    {
        $rows = [$this->row('Retail')];

        $this->assertTrue($this->resolver->isCoveredByRows($rows, $this->packageWithArea('Retail')));
        $this->assertFalse($this->resolver->isCoveredByRows($rows, $this->packageWithArea('Gift Cards')));
        $this->assertFalse($this->resolver->isCoveredByRows($rows, $this->packageFor('Gift', 'Card')), 'Un paquete sin área no queda cubierto por una regla de un área concreta.');
    }

    public function testARowWithoutAnAreaCoversPackagesOfAnyArea(): void
    {
        $this->assertTrue($this->resolver->isCoveredByRows([$this->row(null)], $this->packageWithArea('Retail')));
        $this->assertTrue($this->resolver->isCoveredByRows([$this->row(null, withAreaKey: false)], $this->packageWithArea('Retail')), 'Proyección en caché anterior al área.');
    }
}
