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
 * Pruebas del manejador de la consulta de estado. Nacieron como caracterización (COMR-255); desde COMR-260 paso 2
 * el manejador ya no lanza excepciones para que Messenger reintente: el reintento lo gobierna next_check_at.
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
    public function testDoesNotCheckNorThrowWhenTheSaleWasNotSentToTheProviderYet(?string $stateProcess): void
    {
        $this->sale(CommunicationStateEnum::PENDING, $stateProcess);
        $this->service->expects($this->never())->method('checkStatusSaleInfo');

        // COMR-260 paso 2: no se lanza para que Messenger reintente (cadena sin tope). Una venta aún sin enviar
        // no se vuelve a consultar hasta que pasa a Pending: el cron solo selecciona las ya enviadas.
        ($this->handler)(new CheckSaleMessage(7));

        $this->addToAssertionCount(1);
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

    public function testDoesNotAskMessengerToRetryWhenTheSaleIsStillPendingAfterTheCheck(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->checkLeavesTheSaleIn($sale, CommunicationStateEnum::PENDING);

        // La siguiente consulta la programa next_check_at (espera creciente), no una excepción del manejador.
        ($this->handler)(new CheckSaleMessage(7));

        $this->addToAssertionCount(1);
    }

    public function testDoesNotThrowWhenTheCheckLeavesTheSaleFailed(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->checkLeavesTheSaleIn($sale, CommunicationStateEnum::FAILED);

        ($this->handler)(new CheckSaleMessage(7));

        $this->addToAssertionCount(1);
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

    public function testDoesNotAskMessengerToRetryWhenTheSaleIsUncertain(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->service->method('checkStatusSaleInfo')->willReturnCallback(static function () use ($sale): void {
            $sale->markUncertain();
        });

        ($this->handler)(new CheckSaleMessage(7));

        $this->addToAssertionCount(1); // sin excepción: la vuelve a consultar el cron por next_check_at
    }
}
