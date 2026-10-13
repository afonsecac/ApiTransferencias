<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleInfoRepository;

/**
 * COMR-62 (C3): las consultas que alimentan los bucles de despacho y de consulta de estado se limitan a los
 * subtipos que saben procesar (recarga y paquete). Así, una venta de un subtipo futuro (mercado) no llega a un
 * bucle donde lanzaría y pararía el lote de todas las demás.
 *
 * Hoy no se puede persistir un tercer subtipo (el mapa de discriminadores solo tiene los dos), así que aquí se
 * fija la lista y se comprueba que las consultas siguen devolviendo ambos subtipos con el filtro puesto.
 *
 * @covers \App\Repository\CommunicationSaleInfoRepository
 */
class DispatchableSubtypesFunctionalTest extends ProviderFunctionalTestCase
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

    private function repository(): CommunicationSaleInfoRepository
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);

        return $repository;
    }

    private function sale(CommunicationSaleInfo $sale, string $stateProcess): int
    {
        ++$this->sequence;
        $sale->setTenant($this->account)
            ->setCatalogPackage($this->package)
            ->setPackageId($this->package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('subtype-' . $this->sequence)
            ->setTransactionId('2610120' . str_pad((string) (900 + $this->sequence), 6, '0', STR_PAD_LEFT))
            ->setDestinationAmount($this->package->getDestinationAmount())
            ->setDestinationCurrency($this->package->getDestinationCurrency())
            ->setState(CommunicationStateEnum::PENDING)
            ->setStateProcess($stateProcess);
        if ($sale instanceof CommunicationSaleRecharge) {
            $sale->setPhoneNumber('5550000000');
        }
        $this->em->persist($sale);
        $this->em->flush();

        return (int) $sale->getId();
    }

    /**
     * @param list<CommunicationSaleInfo> $sales
     *
     * @return list<int>
     */
    private function ownIds(array $sales): array
    {
        $ids = [];
        foreach ($sales as $sale) {
            if ($sale->getTenant()?->getId() === $this->account->getId()) {
                $ids[] = (int) $sale->getId();
            }
        }
        sort($ids);

        return $ids;
    }

    public function testTheListOfDispatchableSubtypesIsRechargeAndPackage(): void
    {
        $this->assertSame(
            [CommunicationSaleRecharge::class, CommunicationSalePackage::class],
            CommunicationSaleInfoRepository::DISPATCHABLE_SUBTYPES,
        );
    }

    public function testPendingUndispatchedQueriesStillReturnBothSubtypes(): void
    {
        $recharge = $this->sale(new CommunicationSaleRecharge(), CommunicationStateEnum::CREATED->value);
        $package = $this->sale(new CommunicationSalePackage(), CommunicationStateEnum::CREATED->value);
        $expected = [$recharge, $package];
        sort($expected);

        $this->assertSame($expected, $this->ownIds($this->repository()->findPendingUndispatched()));
        $this->assertSame($expected, $this->ownIds($this->repository()->findPendingUndispatchedByProvider('ETECSA', null)));
        $this->assertGreaterThanOrEqual(2, $this->repository()->countPendingUndispatched());
    }

    public function testAwaitingProviderQueriesStillReturnBothSubtypes(): void
    {
        $recharge = $this->sale(new CommunicationSaleRecharge(), CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $package = $this->sale(new CommunicationSalePackage(), CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $expected = [$recharge, $package];
        sort($expected);

        $this->assertSame($expected, $this->ownIds($this->repository()->findAwaitingProvider()));
        $this->assertGreaterThanOrEqual(2, $this->repository()->countAwaitingProvider());
    }

    public function testTheStatusPollingQueryStillReturnsBothSubtypes(): void
    {
        $recharge = $this->sale(new CommunicationSaleRecharge(), CommunicationStateEnum::PENDING->value);
        $package = $this->sale(new CommunicationSalePackage(), CommunicationStateEnum::PENDING->value);
        $expected = [$recharge, $package];
        sort($expected);

        $this->assertSame($expected, $this->ownIds($this->repository()->getLastPending()));
    }
}
