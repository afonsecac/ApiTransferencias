<?php

namespace App\Tests\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Provider\ProviderResolver
 */
class ProviderResolverTest extends TestCase
{
    private SysConfigRepository&\PHPUnit\Framework\MockObject\MockObject $sysConfigRepo;
    private ClientProviderRoutingRepository&\PHPUnit\Framework\MockObject\MockObject $routingRepo;
    private LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger;
    private ProviderResolver $resolver;

    protected function setUp(): void
    {
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->routingRepo = $this->createMock(ClientProviderRoutingRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resolver = new ProviderResolver($this->sysConfigRepo, $this->routingRepo, $this->logger);
    }

    /** Kill switch en '1' (default cuando no está seteado) salvo que el test diga lo contrario. */
    private function stubRoutingEnabled(bool $enabled): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(function (string $key) use ($enabled) {
                if ($key === ProviderResolver::ROUTING_ENABLED_KEY) {
                    return $enabled ? '1' : '0';
                }

                return null;
            });
    }

    private function accountWithClient(int $clientId, ?Environment $environment = null): Account&\PHPUnit\Framework\MockObject\MockObject
    {
        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn($clientId);

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn($client);
        $account->method('getEnvironment')->willReturn($environment);

        return $account;
    }

    // ---- resolveForAccount ----

    public function testResolveForAccountFallsBackToEtecsaWhenNoDefaultConfiguredAndNoRoutingMatch(): void
    {
        $this->stubRoutingEnabled(true);
        $this->routingRepo->method('findResolvedFor')->willReturn(null);

        $account = $this->accountWithClient(1);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForAccount($account));
    }

    public function testResolveForAccountUsesConfiguredDefaultWhenNoRoutingMatch(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ProviderResolver::DEFAULT_PROVIDER_KEY ? 'DTONE' : '1');
        $this->routingRepo->method('findResolvedFor')->willReturn(null);

        $account = $this->accountWithClient(1);

        $this->assertSame(CommunicationProviderEnum::DTONE, $this->resolver->resolveForAccount($account));
    }

    public function testResolveForAccountUsesRoutingWhenMatchFound(): void
    {
        $this->stubRoutingEnabled(true);

        $routing = $this->createMock(ClientProviderRouting::class);
        $routing->method('getProvider')->willReturn('DTONE');
        $this->routingRepo->method('findResolvedFor')->willReturn($routing);

        $account = $this->accountWithClient(2);

        $this->assertSame(CommunicationProviderEnum::DTONE, $this->resolver->resolveForAccount($account));
    }

    public function testResolveForAccountIgnoresRoutingTableWhenKillSwitchDisabled(): void
    {
        $this->stubRoutingEnabled(false);

        $this->routingRepo->expects($this->never())->method('findResolvedFor');

        $account = $this->accountWithClient(3);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForAccount($account));
    }

    public function testResolveForAccountFallsBackWhenClientIsNull(): void
    {
        $this->stubRoutingEnabled(true);
        $this->routingRepo->expects($this->never())->method('findResolvedFor');

        $account = $this->createMock(Account::class);
        $account->method('getClient')->willReturn(null);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForAccount($account));
    }

    public function testResolveForAccountFallsBackToEtecsaWhenRoutingProviderIsUnknown(): void
    {
        $this->stubRoutingEnabled(true);

        $routing = $this->createMock(ClientProviderRouting::class);
        $routing->method('getProvider')->willReturn('NOT_A_REAL_PROVIDER');
        $this->routingRepo->method('findResolvedFor')->willReturn($routing);

        $account = $this->accountWithClient(4);

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForAccount($account));
    }

    // ---- resolveEffectiveFor (usado por resolveForAccount y por el preview de admin) ----

    public function testResolveEffectiveForUsesRoutingWhenMatchFound(): void
    {
        $this->stubRoutingEnabled(true);

        $routing = $this->createMock(ClientProviderRouting::class);
        $routing->method('getProvider')->willReturn('DTONE');
        $this->routingRepo->method('findResolvedFor')->with(5, 10, 'recharge')->willReturn($routing);

        $this->assertSame(CommunicationProviderEnum::DTONE, $this->resolver->resolveEffectiveFor(5, 10, 'recharge'));
    }

    public function testResolveEffectiveForIgnoresRoutingTableWhenKillSwitchDisabled(): void
    {
        $this->stubRoutingEnabled(false);
        $this->routingRepo->expects($this->never())->method('findResolvedFor');

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveEffectiveFor(5, null, null));
    }

    // ---- resolveForSale ----

    public function testResolveForSaleReadsSnapshotFromSale(): void
    {
        $sale = $this->createMock(CommunicationSaleRecharge::class);
        $sale->method('getProvider')->willReturn('DTONE');

        $this->assertSame(CommunicationProviderEnum::DTONE, $this->resolver->resolveForSale($sale));
    }

    public function testResolveForSaleFallsBackToEtecsaWhenProviderIsNull(): void
    {
        $sale = $this->createMock(CommunicationSaleRecharge::class);
        $sale->method('getProvider')->willReturn(null);
        $sale->method('getId')->willReturn(42);

        $this->logger->expects($this->once())->method('warning');

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForSale($sale));
    }

    public function testResolveForSaleFallsBackToEtecsaWhenProviderIsUnknown(): void
    {
        $sale = $this->createMock(CommunicationSaleRecharge::class);
        $sale->method('getProvider')->willReturn('SOME_LEGACY_VALUE');
        $sale->method('getId')->willReturn(7);

        $this->logger->expects($this->once())->method('warning');

        $this->assertSame(CommunicationProviderEnum::ETECSA, $this->resolver->resolveForSale($sale));
    }

    // ---- allowedForClient ----

    public function testAllowedForClientReturnsDefaultWhenNoRoutingRows(): void
    {
        $this->stubRoutingEnabled(true);
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn([]);

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(1);

        $this->assertSame([CommunicationProviderEnum::ETECSA], $this->resolver->allowedForClient($client));
    }

    public function testAllowedForClientReturnsRoutingProviders(): void
    {
        $this->stubRoutingEnabled(true);
        $this->routingRepo->method('findActiveProviderCodesForClient')->willReturn(['ETECSA', 'DTONE']);

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(2);

        $this->assertEqualsCanonicalizing(
            [CommunicationProviderEnum::ETECSA, CommunicationProviderEnum::DTONE],
            $this->resolver->allowedForClient($client)
        );
    }

    public function testAllowedForClientIgnoresRoutingTableWhenKillSwitchDisabled(): void
    {
        $this->stubRoutingEnabled(false);
        $this->routingRepo->expects($this->never())->method('findActiveProviderCodesForClient');

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(3);

        $this->assertSame([CommunicationProviderEnum::ETECSA], $this->resolver->allowedForClient($client));
    }
}
