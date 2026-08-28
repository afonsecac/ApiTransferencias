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
}
