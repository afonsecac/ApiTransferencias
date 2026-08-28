<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Provider\ProviderDispatchResolver;
use App\Provider\SelectedDispatch;
use App\Repository\SysConfigRepository;
use App\Service\HistoricalSaleService;
use App\Service\Provider\SaleProviderFailoverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Service\Provider\SaleProviderFailoverService
 */
class SaleProviderFailoverServiceTest extends TestCase
{
    private ProviderDispatchResolver&MockObject $dispatchResolver;
    private SysConfigRepository&MockObject $sysConfigRepo;
    private HistoricalSaleService&MockObject $historicalSaleService;
    private SaleProviderFailoverService $service;

    protected function setUp(): void
    {
        $this->dispatchResolver = $this->createMock(ProviderDispatchResolver::class);
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->historicalSaleService = $this->createMock(HistoricalSaleService::class);

        $this->service = new SaleProviderFailoverService(
            $this->dispatchResolver,
            $this->sysConfigRepo,
            $this->historicalSaleService,
        );

        // Kill switch en '1' por defecto salvo que el test lo apague.
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === SaleProviderFailoverService::FAILOVER_ENABLED_KEY ? '1' : null);
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    private function rechargeSale(string $provider = 'CSQ', array $transactionStatus = []): CommunicationSaleRecharge
    {
        $account = $this->createMock(Account::class);
        $package = new CommunicationPackage();

        $sale = new CommunicationSaleRecharge();
        $this->assignId($sale, 1);
        $sale->setTenant($account);
        $sale->setCatalogPackage($package);
        $sale->setProvider($provider);
        $sale->setTransactionStatus($transactionStatus);

        return $sale;
    }

    public function testPromotesToTheFallbackProviderWhenOneIsAvailable(): void
    {
        $sale = $this->rechargeSale('CSQ');
        $product = $this->createMock(CommunicationProduct::class);
        $selected = new SelectedDispatch(CommunicationProviderEnum::DTONE, $product, 'ref-fallback');

        $this->dispatchResolver->expects($this->once())
            ->method('selectExcluding')
            ->with($this->anything(), $this->anything(), 'recharge', [CommunicationProviderEnum::CSQ])
            ->willReturn($selected);
        $this->historicalSaleService->expects($this->once())->method('createHistoricalCommunication');

        $promoted = $this->service->promoteToFallback($sale, 'Provider rejected the recharge');

        $this->assertTrue($promoted);
        $this->assertSame('DTONE', $sale->getProvider());
        $this->assertSame('ref-fallback', $sale->getDispatchExternalRef());
        $this->assertSame('Created', $sale->getStateProcess());
        $this->assertSame('CSQ', $sale->getTransactionStatus()['retry']['failoverFrom']);
        $this->assertSame('DTONE', $sale->getTransactionStatus()['retry']['failoverTo']);
    }

    public function testDoesNotPromoteWhenNoFallbackCandidateIsAvailable(): void
    {
        $sale = $this->rechargeSale('CSQ');

        $this->dispatchResolver->method('selectExcluding')->willReturn(null);
        $this->historicalSaleService->expects($this->never())->method('createHistoricalCommunication');

        $promoted = $this->service->promoteToFallback($sale, 'Provider rejected the recharge');

        $this->assertFalse($promoted);
        $this->assertSame('CSQ', $sale->getProvider());
    }

    public function testDoesNotPromoteTwiceForTheSameSale(): void
    {
        $sale = $this->rechargeSale('CSQ', ['retry' => ['failoverFrom' => 'ETECSA', 'failoverTo' => 'CSQ']]);

        $this->dispatchResolver->expects($this->never())->method('selectExcluding');

        $promoted = $this->service->promoteToFallback($sale, 'Provider rejected the recharge again');

        $this->assertFalse($promoted);
    }

    public function testRespectsTheKillSwitch(): void
    {
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->sysConfigRepo->method('findCachedValue')
            ->willReturnCallback(fn (string $key) => $key === SaleProviderFailoverService::FAILOVER_ENABLED_KEY ? '0' : null);
        $this->service = new SaleProviderFailoverService($this->dispatchResolver, $this->sysConfigRepo, $this->historicalSaleService);

        $sale = $this->rechargeSale('CSQ');
        $this->dispatchResolver->expects($this->never())->method('selectExcluding');

        $promoted = $this->service->promoteToFallback($sale, 'Provider rejected the recharge');

        $this->assertFalse($promoted);
    }

    public function testDoesNotPromoteWithoutACatalogPackageSnapshot(): void
    {
        $account = $this->createMock(Account::class);
        $sale = new CommunicationSaleRecharge();
        $sale->setTenant($account);
        $sale->setProvider('CSQ');

        $this->dispatchResolver->expects($this->never())->method('selectExcluding');

        $this->assertFalse($this->service->promoteToFallback($sale, 'reason'));
    }

    public function testUsesSaleTypeSaleForPackageSales(): void
    {
        $account = $this->createMock(Account::class);
        $package = new CommunicationPackage();
        $sale = new CommunicationSalePackage();
        $this->assignId($sale, 2);
        $sale->setTenant($account);
        $sale->setCatalogPackage($package);
        $sale->setProvider('CSQ');

        $product = $this->createMock(CommunicationProduct::class);
        $selected = new SelectedDispatch(CommunicationProviderEnum::DTONE, $product, 'ref-fallback');

        $this->dispatchResolver->expects($this->once())
            ->method('selectExcluding')
            ->with($this->anything(), $this->anything(), 'sale', [CommunicationProviderEnum::CSQ])
            ->willReturn($selected);

        $this->assertTrue($this->service->promoteToFallback($sale, 'reason'));
    }
}
