<?php

namespace App\Tests\MessageHandler;

use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\MessageHandler\CheckSaleMessageHandler;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Pruebas de caracterización (COMR-255): fijan lo que hace hoy el manejador de la consulta de estado
 * para que un cambio involuntario falle. No describen un comportamiento deseado, sino el actual.
 *
 * @covers \App\MessageHandler\CheckSaleMessageHandler
 */
class CheckSaleMessageHandlerTest extends TestCase
{
    private CommunicationSaleService&MockObject $service;
    private EntityManagerInterface&MockObject $em;
    private EntityRepository&MockObject $repository;
    private CheckSaleMessageHandler $handler;

    protected function setUp(): void
    {
        $this->service = $this->createMock(CommunicationSaleService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->em->method('getRepository')->with(CommunicationSaleInfo::class)->willReturn($this->repository);

        $this->handler = new CheckSaleMessageHandler($this->service, $this->em, $this->createMock(LoggerInterface::class));
    }

    private function sale(CommunicationStateEnum $state, ?string $stateProcess): CommunicationSaleRecharge
    {
        $sale = (new CommunicationSaleRecharge())->setState($state)->setStateProcess($stateProcess);
        $this->repository->method('find')->with(7)->willReturn($sale);

        return $sale;
    }

    public function testDiscardsTheMessageWhenTheSaleDoesNotExist(): void
    {
        $this->repository->method('find')->with(7)->willReturn(null);
        $this->service->expects($this->never())->method('checkStatusSaleInfo');

        ($this->handler)(new CheckSaleMessage(7));
    }

    /**
     * @dataProvider finalOrReservedStates
     */
    public function testDoesNothingWhenTheSaleIsAlreadyFinalOrReserved(CommunicationStateEnum $state): void
    {
        $this->sale($state, 'Pending');
        $this->service->expects($this->never())->method('checkStatusSaleInfo');

        ($this->handler)(new CheckSaleMessage(7));
    }

    /**
     * @return iterable<string, array{CommunicationStateEnum}>
     */
    public static function finalOrReservedStates(): iterable
    {
        yield 'completada' => [CommunicationStateEnum::COMPLETED];
        yield 'rechazada' => [CommunicationStateEnum::REJECTED];
        yield 'reservada' => [CommunicationStateEnum::RESERVED];
    }

    /**
     * @dataProvider notSentYetStateProcesses
     */
    public function testThrowsSoMessengerRetriesWhenTheSaleWasNotSentToTheProviderYet(?string $stateProcess): void
    {
        $this->sale(CommunicationStateEnum::PENDING, $stateProcess);
        $this->service->expects($this->never())->method('checkStatusSaleInfo');

        try {
            ($this->handler)(new CheckSaleMessage(7));
            $this->fail('Debía lanzar para que Messenger reintente.');
        } catch (MyCurrentException $e) {
            $this->assertSame('Sale not yet sent, retry later', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function notSentYetStateProcesses(): iterable
    {
        yield 'sin state process' => [null];
        yield 'CREATED' => ['Created'];
        yield 'SENDING' => ['SENDING'];
        yield 'esperando proveedor' => [CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER];
    }

    private function checkLeavesTheSaleIn(CommunicationSaleRecharge $sale, CommunicationStateEnum $stateAfterCheck): void
    {
        $this->service->expects($this->once())->method('checkStatusSaleInfo')->with(7);
        $this->em->method('refresh')->willReturnCallback(static function (object $entity) use ($stateAfterCheck): void {
            $entity->setState($stateAfterCheck);
        });
    }

    public function testThrowsCheckAgainWhenTheSaleIsStillPendingAfterTheCheck(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->checkLeavesTheSaleIn($sale, CommunicationStateEnum::PENDING);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Check again');

        ($this->handler)(new CheckSaleMessage(7));
    }

    public function testThrowsWhenTheCheckLeavesTheSaleFailed(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->checkLeavesTheSaleIn($sale, CommunicationStateEnum::FAILED);

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionMessage('Failed to process the check sale');

        ($this->handler)(new CheckSaleMessage(7));
    }

    /**
     * @dataProvider endStatesAfterCheck
     */
    public function testFinishesWithoutErrorWhenTheCheckResolvesTheSale(CommunicationStateEnum $stateAfterCheck): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->checkLeavesTheSaleIn($sale, $stateAfterCheck);

        ($this->handler)(new CheckSaleMessage(7));

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{CommunicationStateEnum}>
     */
    public static function endStatesAfterCheck(): iterable
    {
        yield 'completada' => [CommunicationStateEnum::COMPLETED];
        yield 'rechazada' => [CommunicationStateEnum::REJECTED];
    }
}
