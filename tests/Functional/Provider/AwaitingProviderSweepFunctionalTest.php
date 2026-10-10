<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationPackage;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;

/**
 * @covers \App\Service\CommunicationSaleService::dispatchAwaitingProviderSales
 *
 * Contra Postgres real: una venta aceptada sin proveedor (state=PENDING,
 * stateProcess=AWAITING_PROVIDER — ver CommunicationSaleService::admitV2())
 * debe quedarse esperando mientras ningún candidato esté despachable, y en
 * cuanto uno lo esté, resolver proveedor+producto y volver al flujo normal
 * de despacho (stateProcess=CREATED).
 */
class AwaitingProviderSweepFunctionalTest extends ProviderFunctionalTestCase
{
    private function saleService(): CommunicationSaleService
    {
        return self::getContainer()->get(CommunicationSaleService::class);
    }

    /**
     * Fija explícitamente la disponibilidad de ETECSA en TEST: la caché de
     * sys_config sobrevive al rollback de cada test, así que "sin
     * credenciales" hay que declararlo (valor vacío), no asumirlo.
     */
    private function etecsaAvailable(bool $available): void
    {
        $this->setSysConfig('provider.etecsa.test.base_url', $available ? 'https://etecsa.example.test' : '');
        $this->setSysConfig('provider.etecsa.test.api_key', $available ? 'functional-key' : '');
    }

    private function awaitingSale(\App\Entity\Account $account, CommunicationPackage $package, string $suffix, array $extra = []): CommunicationSaleRecharge
    {
        $sale = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setCatalogPackage($package)
            ->setPackageId($package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('func-await-' . $suffix)
            ->setTransactionId('26101001' . $suffix)
            ->setPhoneNumber($extra['phone'] ?? '5550000000')
            ->setDestinationAmount($package->getDestinationAmount())
            ->setDestinationCurrency($package->getDestinationCurrency())
            ->setState(CommunicationStateEnum::PENDING)
            ->setStateProcess(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);

        $this->em->persist($sale);

        return $sale;
    }

    public function testSaleKeepsWaitingWhileNoProviderIsAvailable(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->awaitingSale($account, $package, '00001');
        $this->em->flush();

        $this->etecsaAvailable(false);
        $resolved = $this->saleService()->dispatchAwaitingProviderSales();

        $this->assertSame(0, $resolved);
        $this->em->refresh($sale);
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, $sale->getStateProcess());
        $this->assertNull($sale->getProvider());
        $this->assertNull($sale->getDispatchProduct());
    }

    public function testSaleGetsItsProviderAndReturnsToTheNormalFlowWhenOneBecomesAvailable(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->awaitingSale($account, $package, '00002');
        $this->em->flush();

        $this->etecsaAvailable(true);

        $resolved = $this->saleService()->dispatchAwaitingProviderSales();

        $this->assertSame(1, $resolved);
        $this->em->refresh($sale);
        $this->assertSame('ETECSA', $sale->getProvider());
        $this->assertNotNull($sale->getDispatchProduct());
        $this->assertNotNull($sale->getDispatchExternalRef());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $sale->getStateProcess());
        $this->assertSame(CommunicationStateEnum::PENDING, $sale->getState());
    }

    public function testSaleFailsWhenTheResolvedProductNeedsAnIdentifierTheClientDidNotSend(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $product = $this->em->getRepository(\App\Entity\CommunicationPackageProviderProduct::class)
            ->findOneBy(['communicationPackage' => $package])->getProduct();
        $product->setRequiredIdentifierFields([['accountIdentifier']]);
        $sale = $this->awaitingSale($account, $package, '00003');
        $this->em->flush();

        $this->etecsaAvailable(true);

        $this->saleService()->dispatchAwaitingProviderSales();

        $this->em->refresh($sale);
        $this->assertSame(CommunicationStateEnum::FAILED, $sale->getState());
        $this->assertSame(CommunicationStateEnum::FAILED->value, $sale->getStateProcess());
    }

    /**
     * Pedido del usuario (2026-10-10): una venta que llega cuando ningún
     * proveedor está disponible se acepta (no 409) y se despacha sola en
     * cuanto uno se recupera. Camino real completo contra Postgres:
     * admisión → persistencia sin proveedor → recuperación → despacho.
     */
    public function testRechargeArrivingWithNoProviderAvailableIsAcceptedAndDispatchedOnRecovery(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA', price: 0.0);
        $this->authenticateAs($account);
        $this->etecsaAvailable(false);

        $request = (new CommunicationSaleRecharge())
            ->setPackageId($package->getId())
            ->setClientTransactionId('func-await-e2e')
            ->setPhoneNumber('5550000000');

        // ETECSA sin credenciales para TEST: cubre el destino pero no está disponible.
        $sale = $this->saleService()->processRecharge($request);

        $this->assertNotNull($sale->getId());
        $this->assertSame(CommunicationStateEnum::PENDING, $sale->getState());
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, $sale->getStateProcess());
        $this->assertNull($sale->getProvider());

        $this->etecsaAvailable(true);

        $this->assertSame(1, $this->saleService()->dispatchAwaitingProviderSales());

        $this->em->refresh($sale);
        $this->assertSame('ETECSA', $sale->getProvider());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $sale->getStateProcess());
    }

    /**
     * El botón "Reintentar" del dashboard (tryAgainWithTransaction) no puede
     * despachar una venta sin proveedor/producto: la deja esperando, o la
     * resuelve si ya hay un proveedor disponible.
     */
    public function testManualRetryNeverDispatchesASaleWithoutProvider(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->awaitingSale($account, $package, '00005');
        $this->em->flush();
        $this->etecsaAvailable(false);

        $this->saleService()->tryAgainWithTransaction($sale->getId());

        $this->em->refresh($sale);
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, $sale->getStateProcess());
        $this->assertNull($sale->getProvider());

        $this->etecsaAvailable(true);

        $this->saleService()->tryAgainWithTransaction($sale->getId());

        $this->em->refresh($sale);
        $this->assertSame('ETECSA', $sale->getProvider());
        $this->assertNotNull($sale->getDispatchProduct());
        $this->assertSame(CommunicationStateEnum::CREATED->value, $sale->getStateProcess());
    }

    public function testSaleWithoutPendingStateIsNotTouched(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->awaitingSale($account, $package, '00004');
        $sale->setState(CommunicationStateEnum::COMPLETED);
        $this->em->flush();

        $this->etecsaAvailable(true);

        $this->assertSame(0, $this->saleService()->dispatchAwaitingProviderSales());
        $this->em->refresh($sale);
        $this->assertNull($sale->getProvider());
    }
}
