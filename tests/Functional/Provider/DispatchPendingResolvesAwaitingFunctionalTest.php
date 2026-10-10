<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationsDispatchService;

/**
 * @covers \App\Service\CommunicationsDispatchService
 *
 * Pantalla /settings/communications: el contador debe mostrar las ventas
 * aceptadas sin proveedor (AWAITING_PROVIDER), y "Encolar pendientes" debe
 * intentar resolverlas además de reencolar las ya despachables.
 */
class DispatchPendingResolvesAwaitingFunctionalTest extends ProviderFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setSysConfig(CommunicationsDispatchService::CONFIG_KEY, '1');
    }

    private function dispatchService(): CommunicationsDispatchService
    {
        return self::getContainer()->get(CommunicationsDispatchService::class);
    }

    private function etecsaAvailable(bool $available): void
    {
        $this->setSysConfig('provider.etecsa.test.base_url', $available ? 'https://etecsa.example.test' : '');
        $this->setSysConfig('provider.etecsa.test.api_key', $available ? 'functional-key' : '');
    }

    private function sale(\App\Entity\Account $account, CommunicationPackage $package, string $suffix, string $stateProcess, CommunicationStateEnum $state = CommunicationStateEnum::PENDING): CommunicationSaleRecharge
    {
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setCatalogPackage($package)
            ->setPackageId($package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('func-dispatch-await-' . $suffix)
            ->setTransactionId('26101003' . $suffix)
            ->setPhoneNumber('5550000000')
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setState($state)
            ->setStateProcess($stateProcess);
        $this->em->persist($sale);

        return $sale;
    }

    public function testCountsOnlyPendingSalesAwaitingAProvider(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');

        $before = $this->dispatchService()->countAwaitingProvider();
        $this->sale($account, $package, '00001', CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $this->sale($account, $package, '00002', CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        // No cuentan: ya despachable (CREATED) y esperando pero rechazada.
        $this->sale($account, $package, '00003', CommunicationStateEnum::CREATED->value);
        $this->sale($account, $package, '00004', CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, CommunicationStateEnum::REJECTED);
        $this->em->flush();

        $this->assertSame($before + 2, $this->dispatchService()->countAwaitingProvider());
        // El contador de pendientes de siempre no mezcla las en espera.
        $this->assertSame(1, $this->dispatchService()->countPendingUndispatched());
    }

    public function testDispatchPendingAlsoResolvesSalesAwaitingAProvider(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->sale($account, $package, '00005', CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $this->em->flush();
        $this->etecsaAvailable(true);

        $result = $this->dispatchService()->dispatchPending();

        $this->assertSame(1, $result['awaitingResolved']);
        $this->em->refresh($sale);
        $this->assertSame('ETECSA', $sale->getProvider());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $sale->getStateProcess());
    }

    public function testDispatchPendingLeavesAwaitingSalesWaitingWhileNoProviderIsAvailable(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->sale($account, $package, '00006', CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $this->em->flush();
        $this->etecsaAvailable(false);

        $result = $this->dispatchService()->dispatchPending();

        $this->assertSame(0, $result['awaitingResolved']);
        $this->em->refresh($sale);
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, $sale->getStateProcess());
    }
}
