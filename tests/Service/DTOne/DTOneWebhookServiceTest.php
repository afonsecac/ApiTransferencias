<?php

namespace App\Tests\Service\DTOne;

use App\DTO\DTOneWebhookPayloadDto;
use App\Entity\CommunicationSaleRecharge;
use App\Message\CheckSaleMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Repository\SysConfigRepository;
use App\Service\DTOne\DTOneWebhookService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @covers \App\Service\DTOne\DTOneWebhookService
 */
class DTOneWebhookServiceTest extends TestCase
{
    private SysConfigRepository&MockObject $sysConfigRepo;
    private CommunicationSaleInfoRepository&MockObject $saleInfoRepo;
    private MessageBusInterface&MockObject $messageBus;
    private DTOneWebhookService $service;

    protected function setUp(): void
    {
        $this->sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $this->saleInfoRepo = $this->createMock(CommunicationSaleInfoRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new DTOneWebhookService(
            $this->sysConfigRepo,
            $this->saleInfoRepo,
            $this->messageBus,
            new ArrayAdapter(),
            new NullLogger(),
        );
    }

    private function assignId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    public function testIsValidTokenReturnsFalseWhenNoTokenConfigured(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn(null);

        $this->assertFalse($this->service->isValidToken('anything'));
    }

    public function testIsValidTokenReturnsFalseWhenTokenDoesNotMatch(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn('correct-token');

        $this->assertFalse($this->service->isValidToken('wrong-token'));
    }

    public function testIsValidTokenReturnsTrueWhenTokenMatches(): void
    {
        $this->sysConfigRepo->method('findCachedValue')->willReturn('correct-token');

        $this->assertTrue($this->service->isValidToken('correct-token'));
    }

    public function testHandleDispatchesCheckSaleMessageWhenSaleFound(): void
    {
        $sale = new CommunicationSaleRecharge();
        $this->assignId($sale, 42);

        $this->saleInfoRepo->method('findOneByTransactionId')->with('TX-1')->willReturn($sale);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn (CheckSaleMessage $m) => $m->getSaleId() === 42))
            ->willReturn(new Envelope(new CheckSaleMessage(42)));

        $this->service->handle(new DTOneWebhookPayloadDto(externalId: 'TX-1', eventId: 'evt-1'));
    }

    public function testHandleDoesNothingWhenSaleNotFound(): void
    {
        $this->saleInfoRepo->method('findOneByTransactionId')->willReturn(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->handle(new DTOneWebhookPayloadDto(externalId: 'TX-unknown', eventId: 'evt-2'));
    }

    public function testHandleDoesNothingWhenExternalIdMissing(): void
    {
        $this->saleInfoRepo->expects($this->never())->method('findOneByTransactionId');
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->handle(new DTOneWebhookPayloadDto(externalId: null, eventId: 'evt-3'));
    }

    public function testHandleIgnoresDuplicateDeliveryOfSameEventId(): void
    {
        $sale = new CommunicationSaleRecharge();
        $this->assignId($sale, 7);

        $this->saleInfoRepo->method('findOneByTransactionId')->willReturn($sale);
        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new CheckSaleMessage(7)));

        $payload = new DTOneWebhookPayloadDto(externalId: 'TX-2', eventId: 'evt-dup');

        $this->service->handle($payload);
        // Segunda entrega del mismo evento: no debe volver a despachar.
        $this->service->handle($payload);
    }

    public function testHandleProcessesEachDeliveryWhenNoEventId(): void
    {
        $sale = new CommunicationSaleRecharge();
        $this->assignId($sale, 9);

        $this->saleInfoRepo->method('findOneByTransactionId')->willReturn($sale);
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturn(new Envelope(new CheckSaleMessage(9)));

        $payload = new DTOneWebhookPayloadDto(externalId: 'TX-3', eventId: null);

        $this->service->handle($payload);
        $this->service->handle($payload);
    }
}
