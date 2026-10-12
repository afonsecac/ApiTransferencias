<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleInfoRepository;
use App\Schedule\Task\CheckFailedRechargesTask;
use Psr\Log\NullLogger;

/**
 * Pruebas de caracterización (COMR-255) contra Postgres real: qué ventas selecciona hoy la consulta
 * horaria, qué cuenta como saldo comprometido y qué hace la tarea que revive las ventas FAILED.
 * Describen el comportamiento actual, defectos incluidos; cuando se corrija alguno, su test cambia a propósito.
 *
 * @covers \App\Repository\CommunicationSaleInfoRepository
 * @covers \App\Schedule\Task\CheckFailedRechargesTask
 */
class SalePollingCharacterizationFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;
    private CommunicationPackage $package;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $this->account = $this->createAccount($client, $environment);
        $this->package = $this->createSellableV2Package($environment, 'ETECSA');
    }

    private function sale(CommunicationStateEnum $state, ?string $stateProcess, float $amount = 5.0, ?\DateTimeImmutable $createdAt = null): CommunicationSaleRecharge
    {
        ++$this->sequence;
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount($amount)
            ->setTotalPrice($amount)
            ->setCurrency('USD')
            ->setClientTransactionId('char-' . $this->sequence)
            ->setTransactionId('2610120' . str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT))
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        if (null !== $createdAt) {
            $sale->setCreatedAt($createdAt);
        }
        $this->em->persist($sale);
        $this->em->flush();

        return $sale;
    }

    private function repository(): CommunicationSaleInfoRepository
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);

        return $repository;
    }

    /**
     * @param list<CommunicationSaleInfo> $sales
     *
     * @return list<int>
     */
    private function ids(array $sales): array
    {
        $ids = array_map(static fn (CommunicationSaleInfo $s): int => (int) $s->getId(), $sales);
        sort($ids);

        return $ids;
    }

    // ---- getLastPending: lo que recorre cada hora CheckStatusTask ----

    public function testLastPendingOnlySelectsPendingSalesWhoseStateProcessIsPending(): void
    {
        $pending = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $this->sale(CommunicationStateEnum::PENDING, 'Created');
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING');
        $this->sale(CommunicationStateEnum::PENDING, CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $this->sale(CommunicationStateEnum::PENDING, null);
        $this->sale(CommunicationStateEnum::COMPLETED, 'Completed');
        $this->sale(CommunicationStateEnum::RESERVED, 'Pending');

        $selected = array_filter($this->repository()->getLastPending(), fn (CommunicationSaleInfo $s): bool => $s->getTenant()?->getId() === $this->account->getId());

        $this->assertSame([$pending->getId()], $this->ids(array_values($selected)));
    }

    public function testLastPendingHasNoAgeLimitNorRowLimit(): void
    {
        $recent = $this->sale(CommunicationStateEnum::PENDING, 'Pending');
        $ancient = $this->sale(CommunicationStateEnum::PENDING, 'Pending', 5.0, new \DateTimeImmutable('-400 days'));

        $selected = array_filter($this->repository()->getLastPending(), fn (CommunicationSaleInfo $s): bool => $s->getTenant()?->getId() === $this->account->getId());

        $this->assertSame($this->ids([$recent, $ancient]), $this->ids(array_values($selected)), 'Una venta de hace más de un año se sigue consultando cada hora.');
    }

    public function testRejectedSaleWithPendingStateProcessIsNotPolledNorReserved(): void
    {
        // Las dos ventas reales de producción (5387 y 4793) quedaron así: la incoherencia es inocua hoy
        // porque getLastPending exige state PENDING, pero el dato es contradictorio (COMR-261).
        $rejected = $this->sale(CommunicationStateEnum::REJECTED, 'Pending', 7.0);

        $selected = array_filter($this->repository()->getLastPending(), fn (CommunicationSaleInfo $s): bool => $s->getId() === $rejected->getId());

        $this->assertSame([], array_values($selected));
        $this->assertSame(0.0, $this->repository()->getReservedAmount($this->account->getId()));
    }

    // ---- getReservedAmount: PENDING y RESERVED bloquean saldo, sea cual sea el state process ----

    public function testReservedAmountCountsPendingAndReservedSalesWhateverTheirStateProcess(): void
    {
        $this->sale(CommunicationStateEnum::PENDING, 'Pending', 5.0);
        $this->sale(CommunicationStateEnum::PENDING, CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, 3.0);
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 2.0);
        $this->sale(CommunicationStateEnum::RESERVED, 'Reserved', 4.0);

        $this->assertSame(14.0, $this->repository()->getReservedAmount($this->account->getId()));
    }

    public function testReservedAmountIgnoresFailedCompletedAndRejectedSales(): void
    {
        $this->sale(CommunicationStateEnum::FAILED, 'Pending', 5.0);
        $this->sale(CommunicationStateEnum::COMPLETED, 'Completed', 3.0);
        $this->sale(CommunicationStateEnum::REJECTED, 'Rejected', 2.0);
        $this->sale(CommunicationStateEnum::CREATED, 'Created', 4.0);

        $this->assertSame(0.0, $this->repository()->getReservedAmount($this->account->getId()));
    }

    // ---- CheckFailedRechargesTask: cada 2 minutos pasa TODAS las FAILED a PENDING ----

    private function runFailedRechargesTask(): void
    {
        (new CheckFailedRechargesTask($this->em, new NullLogger()))();
        $this->em->clear();
    }

    public function testTaskMovesEveryFailedSaleToPendingWithoutLookingAtAgeOrStateProcess(): void
    {
        $recent = $this->sale(CommunicationStateEnum::FAILED, 'Pending');
        $ancient = $this->sale(CommunicationStateEnum::FAILED, 'Failed', 5.0, new \DateTimeImmutable('-400 days'));
        $untouchedCompleted = $this->sale(CommunicationStateEnum::COMPLETED, 'Completed');
        $untouchedRejected = $this->sale(CommunicationStateEnum::REJECTED, 'Rejected');
        $ids = [$recent->getId(), $ancient->getId(), $untouchedCompleted->getId(), $untouchedRejected->getId()];

        $this->runFailedRechargesTask();

        $repository = $this->repository();
        $this->assertSame(CommunicationStateEnum::PENDING, $repository->find($ids[0])->getState());
        $this->assertSame(CommunicationStateEnum::PENDING, $repository->find($ids[1])->getState());
        $this->assertSame(CommunicationStateEnum::COMPLETED, $repository->find($ids[2])->getState());
        $this->assertSame(CommunicationStateEnum::REJECTED, $repository->find($ids[3])->getState());
    }

    public function testTaskLeavesStateProcessUntouchedSoOnlyThoseWithPendingProcessAreRePolled(): void
    {
        $withPendingProcess = $this->sale(CommunicationStateEnum::FAILED, 'Pending');
        $withFailedProcess = $this->sale(CommunicationStateEnum::FAILED, 'Failed');
        $ids = [$withPendingProcess->getId(), $withFailedProcess->getId()];

        $this->runFailedRechargesTask();

        $this->assertSame('Pending', $this->repository()->find($ids[0])->getStateProcess());
        $this->assertSame('Failed', $this->repository()->find($ids[1])->getStateProcess());

        $polled = array_filter($this->repository()->getLastPending(), fn (CommunicationSaleInfo $s): bool => $s->getTenant()?->getId() === $this->account->getId());
        $this->assertSame([$ids[0]], $this->ids(array_values($polled)), 'Solo la de state process Pending vuelve a la consulta; la otra queda PENDING sin que nada la procese.');
    }

    public function testTaskTurnsFailedSalesIntoReservedBalance(): void
    {
        $this->sale(CommunicationStateEnum::FAILED, 'Failed', 6.0);
        $this->assertSame(0.0, $this->repository()->getReservedAmount($this->account->getId()));

        $this->runFailedRechargesTask();

        $this->assertSame(6.0, $this->repository()->getReservedAmount($this->account->getId()), 'Al pasar a PENDING la venta fallida empieza a bloquear saldo del cliente.');
    }

    public function testTaskDoesNothingWhenThereAreNoFailedSales(): void
    {
        $pending = $this->sale(CommunicationStateEnum::PENDING, 'Pending');

        $this->runFailedRechargesTask();

        $this->assertSame(CommunicationStateEnum::PENDING, $this->repository()->find($pending->getId())->getState());
    }
}
