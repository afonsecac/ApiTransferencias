<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Account;
use App\Entity\Client;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\CatalogVersionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\CatalogVersionResolver
 */
class CatalogVersionResolverTest extends TestCase
{
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CatalogVersionResolver $resolver;

    protected function setUp(): void
    {
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->resolver = new CatalogVersionResolver($this->sysConfigRepo);
    }

    private function accountWithClient(?int $clientId): Account&MockObject
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

    public function testIsV2FalseWhenNeitherKeyIsSet(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn(null);

        $this->assertFalse($this->resolver->isV2($this->accountWithClient(1)));
    }

    public function testIsV2TrueWhenGlobalDefaultIsV2(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === CatalogVersionResolver::DEFAULT_VERSION_KEY ? 'v2' : null);

        $this->assertTrue($this->resolver->isV2($this->accountWithClient(1)));
    }

    public function testIsV2FalseWhenGlobalDefaultIsSomethingOtherThanV2(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === CatalogVersionResolver::DEFAULT_VERSION_KEY ? 'legacy' : null);

        $this->assertFalse($this->resolver->isV2($this->accountWithClient(1)));
    }

    public function testIsV2TrueWhenClientIsInTheExplicitPilotList(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === CatalogVersionResolver::V2_CLIENTS_KEY ? '3, 7, 12' : null);

        $this->assertTrue($this->resolver->isV2($this->accountWithClient(7)));
        $this->assertFalse($this->resolver->isV2($this->accountWithClient(8)));
    }

    public function testExplicitPilotListWinsOverGlobalDefault(): void
    {
        // El default global es legacy, pero el cliente 7 está pilotando V2
        // explícitamente.
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => match ($key) {
                CatalogVersionResolver::DEFAULT_VERSION_KEY => 'legacy',
                CatalogVersionResolver::V2_CLIENTS_KEY => '7',
                default => null,
            });

        $this->assertTrue($this->resolver->isV2($this->accountWithClient(7)));
    }

    public function testIsV2FalseWhenAccountHasNoClient(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === CatalogVersionResolver::V2_CLIENTS_KEY ? '1' : null);

        $this->assertFalse($this->resolver->isV2($this->accountWithClient(null)));
    }
}
