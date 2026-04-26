<?php

namespace App\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\DTO\AccountBalanceDto;
use App\Entity\Account;
use App\Repository\BalanceOperationRepository;
use App\State\BalanceProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @covers \App\State\BalanceProvider
 */
class BalanceProviderTest extends TestCase
{
    private Security&MockObject $security;
    private BalanceOperationRepository&MockObject $operationRepository;
    private BalanceProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->operationRepository = $this->createMock(BalanceOperationRepository::class);

        $this->provider = new BalanceProvider($this->security, $this->operationRepository);
    }

    public function testProvideReturnsBalanceForAccountUser(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(42);
        $account->method('getContractCurrency')->willReturn('EUR');

        $this->security->method('getUser')->willReturn($account);
        $this->operationRepository->method('getBalanceOutput')->with(42)->willReturn(1500.1234);

        $operation = new Get();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame(1500.1234, $result->amount);
    }

    public function testProvideReturnsUsdDefaultWhenCurrencyIsNull(): void
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn(1);
        $account->method('getContractCurrency')->willReturn(null);

        $this->security->method('getUser')->willReturn($account);
        $this->operationRepository->method('getBalanceOutput')->with(1)->willReturn(250.0);

        $operation = new Get();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
    }

    public function testProvideReturnsDefaultForCollectionOperation(): void
    {
        $account = $this->createMock(Account::class);
        $this->security->method('getUser')->willReturn($account);

        $operation = new GetCollection();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
        $this->assertSame(0.0, $result->amount);
    }

    public function testProvideReturnsDefaultForNonAccountUser(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $this->security->method('getUser')->willReturn($user);

        $operation = new Get();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
        $this->assertSame(0.0, $result->amount);
    }

    public function testProvideReturnsDefaultWhenUserIsNull(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $operation = new Get();
        $result = $this->provider->provide($operation);

        $this->assertInstanceOf(AccountBalanceDto::class, $result);
        $this->assertSame('USD', $result->currency);
        $this->assertSame(0.0, $result->amount);
    }
}
