<?php

namespace App\Tests\Service\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Provider\ProviderDispatchResolver;
use App\Repository\SysConfigRepository;
use App\Service\HistoricalSaleService;
use App\Service\Provider\SaleProviderFailoverService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de caracterización (COMR-263): el failover solo distingue «recarga» de «lo demás». Cualquier
 * subtipo que no sea recarga se enruta como tipo de venta 'sale', igual que un paquete. Cuando se añada el
 * subtipo de mercado (C3/hito 4) esto debe dejar de ser implícito.
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

    public function testAnyOtherSubtypeIsAlsoRoutedAsTheSaleTypeWithoutFailing(): void
    {
        $this->dispatchResolver->expects($this->once())->method('selectExcluding')
            ->with($this->anything(), $this->anything(), 'sale', $this->anything())
            ->willReturn(null);

        $this->assertFalse($this->service->promoteToFallback($this->sale(new CommunicationSaleInfo()), 'rechazo'));
    }
}
