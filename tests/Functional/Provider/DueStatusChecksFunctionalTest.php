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
 * COMR-260 paso 2: la consulta de estado la dirige next_check_at. El cron reclama (con un arrendamiento) las ventas
 * que toca consultar, con tope por proveedor, y encola un CheckSaleMessage por cada una. Contra Postgres real.
 *
 * @covers \App\Repository\CommunicationSaleInfoRepository::claimDueForCheck
 * @covers \App\Service\CommunicationSaleService::dispatchDueStatusChecks
 */
class DueStatusChecksFunctionalTest extends ProviderFunctionalTestCase
{
    private Account $account;
    private CommunicationPackage $package;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $environment = $this->createEnvironment();
        $this->account = $this->createAccount($this->createClient(), $environment);
        $this->package = $this->createSellableV2Package($environment, 'ETECSA');
    }

    private function sale(CommunicationStateEnum $state, string $stateProcess, ?string $nextCheckAt, string $provider = 'ETECSA'): CommunicationSaleRecharge
    {
        ++$this->sequence;
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount(5.0)
            ->setTotalPrice(5.0)
            ->setCurrency('USD')
            ->setProvider($provider)
            ->setClientTransactionId('due-' . $this->sequence)
            ->setTransactionId('2610150' . str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT))
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);
        $this->em->flush();
        // El PrePersist/PreFlush de la entidad manejan fechas, pero next_check_at no: se fija con SQL para ser explícitos.
        $this->em->getConnection()->executeStatement(
            'UPDATE communication_sale_info SET next_check_at = :n WHERE id = :id',
            ['n' => $nextCheckAt, 'id' => $sale->getId()],
        );

        return $sale;
    }

    private function repository(): CommunicationSaleInfoRepository
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);

        return $repository;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-15 12:00:00');
    }

    /** @return list<int> */
    private function claim(int $perProvider = 20, int $lease = 90): array
    {
        $ids = $this->repository()->claimDueForCheck($this->now(), $perProvider, $lease);
        sort($ids);

        return $ids;
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async_check_status');
        \assert($transport instanceof InMemoryTransport);

        return $transport;
    }

    public function testClaimsPendingAndUncertainSalesWhoseCheckIsDue(): void
    {
        $due = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:59:00');
        $uncertain = $this->sale(CommunicationStateEnum::PENDING, CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN, '2026-10-15 12:00:00');
        $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 12:00:01');
        $this->sale(CommunicationStateEnum::COMPLETED, 'Completed', '2026-10-15 11:00:00');
        $this->sale(CommunicationStateEnum::REJECTED, 'Rejected', '2026-10-15 11:00:00');
        $this->sale(CommunicationStateEnum::PENDING, 'Created', '2026-10-15 11:00:00');
        $this->sale(CommunicationStateEnum::PENDING, 'SENDING', '2026-10-15 11:00:00');
        $this->sale(CommunicationStateEnum::PENDING, CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, '2026-10-15 11:00:00');

        $this->assertSame($this->sorted([$due->getId(), $uncertain->getId()]), $this->claim());
    }

    public function testSalesWithoutNextCheckAtAreStillQueried(): void
    {
        $legacy = $this->sale(CommunicationStateEnum::PENDING, 'Pending', null);

        $this->assertSame([$legacy->getId()], $this->claim(), 'Una venta anterior al paso 1, sin next_check_at, no se queda sin consultar.');
    }

    public function testAClaimedSaleIsLeasedSoAnotherWorkerDoesNotTakeItAtTheSameTime(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:59:00');

        $this->assertSame([$sale->getId()], $this->claim(20, 90));
        $this->assertSame([], $this->claim(20, 90), 'Un segundo worker no recibe la misma venta mientras dura el arrendamiento.');

        $leasedUntil = $this->em->getConnection()->fetchOne('SELECT next_check_at FROM communication_sale_info WHERE id = :id', ['id' => $sale->getId()]);
        $this->assertSame('2026-10-15 12:01:30', (new \DateTimeImmutable($leasedUntil))->format('Y-m-d H:i:s'));
    }

    public function testALeaseThatExpiresMakesTheSaleClaimableAgain(): void
    {
        $sale = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:59:00');
        $this->claim(20, 90);

        $later = $this->now()->modify('+2 minutes');

        $this->assertSame([$sale->getId()], $this->repository()->claimDueForCheck($later, 20, 90), 'Si el worker murió, la venta vuelve a tocar tras el arrendamiento.');
    }

    public function testTheLimitIsAppliedPerProviderSoOneProviderCannotStarveTheOthers(): void
    {
        for ($i = 0; $i < 25; ++$i) {
            $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:00:00', 'ETECSA');
        }
        $csq = [];
        for ($i = 0; $i < 3; ++$i) {
            $csq[] = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:30:00', 'CSQ')->getId();
        }

        $claimed = $this->claim(20);

        $this->assertCount(23, $claimed, '20 de ETECSA (tope) + las 3 de CSQ.');
        $this->assertSame($this->sorted($csq), array_values(array_intersect($claimed, $csq)));
    }

    public function testTheOldestDueSalesGoFirstWhenTheLimitCuts(): void
    {
        $newer = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:59:00');
        $older = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 10:00:00');

        $this->assertSame([$older->getId()], $this->claim(1));
        $this->assertNotContains($newer->getId(), [$older->getId()]);
    }

    public function testClaimingManyDueSalesStaysWithinTheLimitsAndUsesLittleMemory(): void
    {
        $template = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:00:00');
        // 600 ventas vencidas más, clonadas por SQL (más rápido que 600 persist()).
        $this->em->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO communication_sale_info
            SELECT (jsonb_populate_record(NULL::communication_sale_info, to_jsonb(s) || jsonb_build_object(
                'id', nextval('communication_sale_info_id_seq'),
                'client_transaction_id', 'bulk-' || g,
                'transaction_id', '26101' || lpad(g::text, 8, '0'),
                'access_token', md5(g::text) || md5((g + 1)::text)
            ))).*
            FROM communication_sale_info s, generate_series(1, 600) g
            WHERE s.id = :id
        SQL, ['id' => $template->getId()]);
        gc_collect_cycles();
        $before = memory_get_usage();

        $claimed = $this->repository()->claimDueForCheck($this->now(), 20, 90);

        $this->assertCount(20, $claimed, 'Aunque haya 601 vencidas, una pasada reclama como mucho el tope por proveedor.');
        $this->assertLessThan(5 * 1024 * 1024, memory_get_usage() - $before, 'La pasada del cron maneja identificadores, no entidades: el consumo no crece con el histórico.');
    }

    public function testDispatchingEnqueuesOneStatusCheckPerClaimedSale(): void
    {
        $a = $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2026-10-15 11:00:00');
        $b = $this->sale(CommunicationStateEnum::PENDING, 'Pending', null);
        $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2099-01-01 00:00:00');
        $this->transport()->reset();

        $count = self::getContainer()->get(CommunicationSaleService::class)->dispatchDueStatusChecks(20, 90, $this->now());

        $this->assertSame(2, $count);
        $sent = $this->transport()->getSent();
        $this->assertCount(2, $sent);
        $ids = array_map(static fn ($e): int => $e->getMessage()->getSaleId(), $sent);
        sort($ids);
        $this->assertSame($this->sorted([$a->getId(), $b->getId()]), $ids);
        $this->assertContainsOnlyInstancesOf(CheckSaleMessage::class, array_map(static fn ($e): object => $e->getMessage(), $sent));
    }

    public function testDispatchingDoesNothingWhenNothingIsDue(): void
    {
        $this->sale(CommunicationStateEnum::PENDING, 'Pending', '2099-01-01 00:00:00');
        $this->transport()->reset();

        $this->assertSame(0, self::getContainer()->get(CommunicationSaleService::class)->dispatchDueStatusChecks(20, 90, $this->now()));
        $this->assertCount(0, $this->transport()->getSent());
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
