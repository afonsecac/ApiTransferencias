<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Exception\MyCurrentException;
use App\Provider\ProviderDispatchResolver;
use App\Repository\SysConfigRepository;
use App\Service\HistoricalSaleService;
use App\Service\Provider\SaleProviderFailoverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * COMR-62 (C3): el failover distingue recarga ('recharge') de paquete ('sale') y lanza ante cualquier otro
 * subtipo, en vez de enrutarlo en silencio como 'sale' (comportamiento que fijó la caracterización de COMR-263).
 *
 * @covers \App\Service\Provider\SaleProviderFailoverService
 */
class SaleProviderFailoverServiceSubtypeTest extends TestCase
{
    private ProviderDispatchResolver&MockObject $dispatchResolver;
    private SaleProviderFailoverService $service;

    protected function setUp(): void
    {
        $this->dispatchResolver = $this->createMock(ProviderDispatchResolver::class);
        $sysConfig = $this->createMock(SysConfigRepository::class);
        $sysConfig->method('findCachedValue')->willReturn('1');

        $this->service = new SaleProviderFailoverService($this->dispatchResolver, $sysConfig, $this->createMock(HistoricalSaleService::class));
    }

    private function sale(CommunicationSaleInfo $sale): CommunicationSaleInfo
    {
        (new \ReflectionProperty($sale, 'id'))->setValue($sale, 1);
        $sale->setTenant($this->createMock(Account::class));
        $sale->setCatalogPackage(new CommunicationPackage());
        $sale->setProvider('CSQ');
        $sale->setTransactionStatus([]);

        return $sale;
    }

    public function testAPackageSaleIsRoutedAsTheSaleType(): void
    {
        $this->dispatchResolver->expects($this->once())->method('selectExcluding')
            ->with($this->anything(), $this->anything(), 'sale', $this->anything())
            ->willReturn(null);

        $this->assertFalse($this->service->promoteToFallback($this->sale(new CommunicationSalePackage()), 'rechazo'));
    }

    public function testAnyOtherSubtypeThrowsInsteadOfBeingRoutedAsASale(): void
    {
        $this->dispatchResolver->expects($this->never())->method('selectExcluding');

        $this->expectException(MyCurrentException::class);

        $this->service->promoteToFallback($this->sale(new CommunicationSaleInfo()), 'rechazo');
    }
}
