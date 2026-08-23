<?php

namespace App\Tests\Service\Catalog;

use App\Entity\Account;
use App\Entity\Client;
use App\Repository\SysConfigRepository;
use App\Service\Catalog\ContractGatingScopeResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Catalog\ContractGatingScopeResolver
 */
class ContractGatingScopeResolverTest extends TestCase
{
    private SysConfigRepository&MockObject $sysConfigRepo;
    private ContractGatingScopeResolver $resolver;

    protected function setUp(): void
    {
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->resolver = new ContractGatingScopeResolver($this->sysConfigRepo);
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

    public function testIsCategoryScopedFalseWhenNeitherKeyIsSet(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn(null);

        $this->assertFalse($this->resolver->isCategoryScoped($this->accountWithClient(1)));
    }

    public function testIsCategoryScopedTrueWhenGlobalDefaultIsCategory(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ContractGatingScopeResolver::SCOPE_KEY ? 'category' : null);

        $this->assertTrue($this->resolver->isCategoryScoped($this->accountWithClient(1)));
    }

    public function testIsCategoryScopedFalseWhenGlobalDefaultIsSomethingOtherThanCategory(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ContractGatingScopeResolver::SCOPE_KEY ? 'tenant' : null);

        $this->assertFalse($this->resolver->isCategoryScoped($this->accountWithClient(1)));
    }

    public function testIsCategoryScopedTrueWhenClientIsInTheExplicitPilotList(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ContractGatingScopeResolver::PILOT_CLIENTS_KEY ? '3, 7, 12' : null);

        $this->assertTrue($this->resolver->isCategoryScoped($this->accountWithClient(7)));
        $this->assertFalse($this->resolver->isCategoryScoped($this->accountWithClient(8)));
    }

    public function testExplicitPilotListWinsOverGlobalDefault(): void
    {
        // El default global es "tenant" (regla vieja), pero el cliente 7
        // está pilotando "category" explícitamente.
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => match ($key) {
                ContractGatingScopeResolver::SCOPE_KEY => 'tenant',
                ContractGatingScopeResolver::PILOT_CLIENTS_KEY => '7',
                default => null,
            });

        $this->assertTrue($this->resolver->isCategoryScoped($this->accountWithClient(7)));
    }

    public function testIsCategoryScopedFalseWhenAccountHasNoClient(): void
    {
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === ContractGatingScopeResolver::PILOT_CLIENTS_KEY ? '1' : null);

        $this->assertFalse($this->resolver->isCategoryScoped($this->accountWithClient(null)));
    }
}
