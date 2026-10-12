<?php

namespace App\Tests\Service;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
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
 * silencio. Las pruebas describen el comportamiento actual; cuando se añada la rama que lanza una excepción
 * ante un subtipo desconocido (C3), los tests de «subtipo desconocido» cambian a propósito.
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

    public function testASaleOfAnyOtherSubtypeIsSilentlyNotQueued(): void
    {
        $this->repository->method('findPendingUndispatched')->willReturn([
            $this->withId(new CommunicationSaleInfo(), 3, '2610120000003'),
        ]);

        $result = $this->service->dispatchPending();

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $this->dispatched, 'No se encola nada ni se avisa: la venta se queda en CREATED sin que nadie lo vea.');
    }

    public function testAnUnknownSubtypeStillAppearsInTheSummaryEmailEvenThoughNothingWasQueuedForIt(): void
    {
        $this->repository->method('findPendingUndispatched')->willReturn([
            $this->withId(new CommunicationSaleRecharge(), 1, '2610120000001'),
            $this->withId(new CommunicationSaleInfo(), 3, '2610120000003'),
        ]);

        $this->service->dispatchPending();

        $summary = array_values(array_filter($this->dispatched, static fn (object $m): bool => $m instanceof DispatchPendingEmailMessage))[0];
        $this->assertSame(1, $summary->getTotal());
        $this->assertSame(['2610120000001', '2610120000003'], $summary->getTransactionIds(), 'El correo lista un transactionId que nunca se encoló.');
    }

    public function testRedispatchAfterAProviderRecoversQueuesRechargesAndPackagesAndIgnoresOtherSubtypes(): void
    {
        $this->repository->method('findPendingUndispatchedByProvider')->willReturn([
            $this->withId(new CommunicationSaleRecharge(), 1, '2610120000001'),
            $this->withId(new CommunicationSalePackage(), 2, '2610120000002'),
            $this->withId(new CommunicationSaleInfo(), 3, '2610120000003'),
        ]);

        $count = $this->service->redispatchPendingFor('CSQ', 'PROD');

        $this->assertSame(2, $count);
        $this->assertSame([SaleRechargeMessage::class, SalePackageMessage::class], $this->dispatchedTypes());
    }
}
