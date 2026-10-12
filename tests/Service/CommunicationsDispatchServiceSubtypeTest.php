<?php

namespace App\Tests\Service;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use App\Message\DispatchPendingEmailMessage;
use App\Message\SalePackageMessage;
use App\Message\SaleRechargeMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\SysConfigRepository;
use App\Service\CommunicationSaleService;
use App\Service\CommunicationsDispatchService;
use App\Service\Provider\ProviderAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Pruebas de caracterización (COMR-263): cómo despacha hoy CommunicationsDispatchService según el subtipo de
 * venta. Hay una rama para recarga y otra para paquete, y NINGUNA para cualquier otro subtipo: se ignora en
 * silencio. COMR-62 (C3) convirtió ese silencio en una excepción: los tests de «subtipo desconocido» cambiaron a
 * propósito respecto a la caracterización de COMR-263.
 *
 * @covers \App\Service\CommunicationsDispatchService
 */
class CommunicationsDispatchServiceSubtypeTest extends TestCase
{
    private CommunicationSaleInfoRepository&MockObject $repository;
    private ProviderAvailabilityService&MockObject $availability;
    private CommunicationSaleService&MockObject $saleService;
    private CommunicationsDispatchService $service;
    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CommunicationSaleInfoRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->repository);

        $sysConfig = $this->createMock(SysConfigRepository::class);
        $sysConfig->method('findCachedValue')->willReturn('1');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope(new \stdClass());
        });

        $this->availability = $this->createMock(ProviderAvailabilityService::class);
        $this->availability->method('canDispatchTo')->willReturn(true);
        $this->saleService = $this->createMock(CommunicationSaleService::class);
        $this->saleService->method('dispatchAwaitingProviderSales')->willReturn(0);

        $this->service = new CommunicationsDispatchService($em, $sysConfig, $bus, $this->availability, $this->saleService, new NullLogger());
    }

    private function withId(CommunicationSaleInfo $sale, int $id, string $transactionId): CommunicationSaleInfo
    {
        (new \ReflectionProperty($sale, 'id'))->setValue($sale, $id);
        $sale->setTransactionId($transactionId);

        return $sale;
    }

    /** @return list<class-string> */
    private function dispatchedTypes(): array
    {
        return array_map(static fn (object $m): string => $m::class, $this->dispatched);
    }

    public function testQueuesARechargeMessageForARechargeAndAPackageMessageForAPackage(): void
    {
        $this->repository->method('findPendingUndispatched')->willReturn([
            $this->withId(new CommunicationSaleRecharge(), 1, '2610120000001'),
            $this->withId(new CommunicationSalePackage(), 2, '2610120000002'),
        ]);

        $result = $this->service->dispatchPending('admin@example.test');

        $this->assertSame(1, $result['recharges']);
        $this->assertSame(1, $result['packages']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(
            [SaleRechargeMessage::class, SalePackageMessage::class, DispatchPendingEmailMessage::class],
            $this->dispatchedTypes(),
        );
    }

    public function testASaleOfAnyOtherSubtypeThrowsInsteadOfBeingSilentlyIgnored(): void
    {
        $this->repository->method('findPendingUndispatched')->willReturn([
            $this->withId(new CommunicationSaleInfo(), 3, '2610120000003'),
        ]);

        try {
            $this->service->dispatchPending();
            $this->fail('Un subtipo desconocido debe lanzar.');
        } catch (MyCurrentException $e) {
            $this->assertSame('UNSUPPORTED_SALE_SUBTYPE', $e->getCodeWork());
        }

        $this->assertSame([], $this->dispatched, 'No se encola nada ni se manda un correo resumen engañoso.');
    }

    public function testRedispatchAfterAProviderRecoversQueuesRechargesAndPackages(): void
    {
        $this->repository->method('findPendingUndispatchedByProvider')->willReturn([
            $this->withId(new CommunicationSaleRecharge(), 1, '2610120000001'),
            $this->withId(new CommunicationSalePackage(), 2, '2610120000002'),
        ]);

        $count = $this->service->redispatchPendingFor('CSQ', 'PROD');

        $this->assertSame(2, $count);
        $this->assertSame([SaleRechargeMessage::class, SalePackageMessage::class], $this->dispatchedTypes());
    }

    public function testRedispatchOfAnotherSubtypeThrowsInsteadOfBeingSilentlyIgnored(): void
    {
        $this->repository->method('findPendingUndispatchedByProvider')->willReturn([
            $this->withId(new CommunicationSaleInfo(), 3, '2610120000003'),
        ]);

        $this->expectException(MyCurrentException::class);

        $this->service->redispatchPendingFor('CSQ', 'PROD');
    }
}
