<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Message\CheckSaleMessage;
use App\Repository\CommunicationSaleInfoRepository;
use App\Service\CommunicationSaleService;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * COMR-257: una venta que se queda en SENDING (el proceso murió tras reclamarla) no se reenvía nunca, pero
 * tampoco se consultaba. El rescate la marca incierta y la consulta por nuestro transactionId, sin reenviar.
 *
 * @covers \App\Service\CommunicationSaleService::rescueStuckSendingSales
 * @covers \App\Repository\CommunicationSaleInfoRepository::findStuckSending
 */
class StuckSendingRescueFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;
    private CommunicationPackage $package;
    private int $sequence = 0;
    /** @var array<int, int> minutos de antigüedad por id de venta, aplicados con ageSales() */
    private array $ages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $environment = $this->createEnvironment();
        $this->account = $this->createAccount($this->createClient(), $environment);
        $this->package = $this->createSellableV2Package($environment, 'ETECSA');
    }

    private function sale(CommunicationStateEnum $state, string $stateProcess, int $minutesOld, float $amount = 5.0): CommunicationSaleRecharge
    {
        ++$this->sequence;
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount($amount)
            ->setTotalPrice($amount)
            ->setCurrency('USD')
            ->setClientTransactionId('stuck-' . $this->sequence)
            ->setTransactionId('2610130' . str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT))
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);
        $this->em->flush();
        $this->ages[(int) $sale->getId()] = $minutesOld;

        return $sale;
    }

    /**
     * El PreFlush de la entidad fija updatedAt a «ahora» en cada flush, así que la antigüedad se escribe
     * al final, con todas las ventas ya creadas, y se vacía el EntityManager para releerlas.
     */
    private function ageSales(): void
    {
        foreach ($this->ages as $id => $minutes) {
            $this->em->getConnection()->executeStatement(
                'UPDATE communication_sale_info SET updated_at = :updatedAt WHERE id = :id',
                ['updatedAt' => (new \DateTimeImmutable(sprintf('-%d minutes', $minutes)))->format('Y-m-d H:i:s'), 'id' => $id],
            );
        }
        $this->ages = [];
        $this->em->clear();
    }

    private function service(): CommunicationSaleService
    {
        return self::getContainer()->get(CommunicationSaleService::class);
    }

    private function repository(): CommunicationSaleInfoRepository
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);

        return $repository;
    }

    private function transport(string $name): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.' . $name);
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }

    public function testOnlyOldSendingSalesOfAPendingStateAreRescued(): void
    {
        $stuck = $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 30);
        $recent = $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 1);
        $notSending = $this->sale(CommunicationStateEnum::PENDING, 'Pending', 30);
        $completed = $this->sale(CommunicationStateEnum::COMPLETED, 'SENDING', 30);

        $this->ageSales();
        $rescued = $this->service()->rescueStuckSendingSales(15);
        $this->assertSame(1, $rescued);
        $find = fn (CommunicationSaleInfo $s): ?string => $this->repository()->find($s->getId())?->getStateProcess();
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, $find($stuck));
        $this->assertSame('SENDING', $find($recent), 'Una venta que lleva poco en SENDING puede seguir enviándose.');
        $this->assertSame('Pending', $find($notSending));
        $this->assertSame('SENDING', $find($completed));
    }

    public function testRescuedSaleIsQueriedButNeverResent(): void
    {
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 30);
        $this->transport('async_check_status')->reset();
        $this->transport('async_clients_high')->reset();

        $this->ageSales();
        $this->service()->rescueStuckSendingSales(15);

        $queries = $this->transport('async_check_status')->getSent();
        $this->assertCount(1, $queries);
        $this->assertInstanceOf(CheckSaleMessage::class, $queries[0]->getMessage());
        $this->assertCount(0, $this->transport('async_clients_high')->getSent(), 'No se despacha ningún envío.');
    }

    public function testRescuedSaleKeepsItsAmountReserved(): void
    {
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 30, 9.0);

        $this->ageSales();
        $this->service()->rescueStuckSendingSales(15);
        $this->em->clear();

        $this->assertSame(9.0, $this->repository()->getReservedAmount($this->account->getId()));
    }

    public function testRunningTheRescueTwiceChangesNothingTheSecondTime(): void
    {
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 30);

        $this->ageSales();
        $this->assertSame(1, $this->service()->rescueStuckSendingSales(15));
        $this->assertSame(0, $this->service()->rescueStuckSendingSales(15));
    }

    public function testTheBatchLimitCapsHowManySalesAreRescuedPerRun(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->sale(CommunicationStateEnum::PENDING, 'SENDING', 30);
        }

        $this->ageSales();
        $this->assertSame(2, $this->service()->rescueStuckSendingSales(15, 2));
    }
}
