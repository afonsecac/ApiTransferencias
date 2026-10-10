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
        // REJECTED y no FAILED: CheckFailedRechargesTask devuelve toda venta
        // FAILED a PENDING cada 2 min, y una venta que nunca salió (sin
        // proveedor) acabaría consultándose a ETECSA.
        $this->assertSame(CommunicationStateEnum::REJECTED, $sale->getState());
        $this->assertSame(CommunicationStateEnum::REJECTED->value, $sale->getStateProcess());
        $this->assertNull($sale->getProvider());
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

    /**
     * Hallazgo de revisión: dos barridos concurrentes (ping/cron + el
     * Reintentar HTTP) leían la misma venta y el segundo flush pisaba el
     * SENDING del primero → doble envío al proveedor. La asignación debe ser
     * un UPDATE condicionado a que siga en AWAITING_PROVIDER.
     */
    public function testAssigningTheProviderNeverOverwritesASaleAnotherSweepAlreadyTook(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $sale = $this->awaitingSale($account, $package, '00006');
        $this->em->flush();

        // El otro barrido ya la tomó y el worker la está enviando; esta
        // instancia en memoria sigue creyendo que espera proveedor.
        $this->em->getConnection()->executeStatement(
            "UPDATE communication_sale_info SET provider = 'ETECSA', state_process = 'SENDING' WHERE id = :id",
            ['id' => $sale->getId()]
        );
        $product = $this->em->getRepository(\App\Entity\CommunicationPackageProviderProduct::class)
            ->findOneBy(['communicationPackage' => $package])->getProduct();

        $claim = new \ReflectionMethod(CommunicationSaleService::class, 'claimAwaitingProvider');
        $won = $claim->invoke(
            $this->saleService(),
            $sale,
            new \App\Provider\SelectedDispatch(\App\Enums\CommunicationProviderEnum::CSQ, $product, 'otra-ref')
        );

        $this->assertFalse($won);
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT provider, state_process, dispatch_external_ref FROM communication_sale_info WHERE id = :id',
            ['id' => $sale->getId()]
        );
        $this->assertSame('ETECSA', $row['provider']);
        $this->assertSame('SENDING', $row['state_process']);
        $this->assertNull($row['dispatch_external_ref']);
    }

    /**
     * Hallazgo de revisión: el Reintentar del dashboard para una venta de
     * PAQUETE en espera forzaba CREATED y encolaba el mensaje sin proveedor
     * ni producto (y la sacaba para siempre del barrido).
     */
    public function testDashboardRetryOfAPackageSaleAwaitingProviderNeverDispatchesIt(): void
    {
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, 'ETECSA');
        $this->etecsaAvailable(false);

        $sale = (new \App\Entity\CommunicationSalePackage())
            ->setTenant($account)
            ->setCatalogPackage($package)
            ->setPackageId($package->getId())
            ->setAmount(5.0)
            ->setCurrency('USD')
            ->setClientTransactionId('func-await-pkg')
            ->setTransactionId('26101002' . '00007')
            ->setState(CommunicationStateEnum::PENDING)
            ->setStateProcess(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER);
        $this->em->persist($sale);
        $this->em->flush();

        $controller = self::getContainer()->get(\App\Controller\DashboardSalesController::class);
        $controller->retry($sale->getId(), new \App\DTO\RetrySaleDto());

        $this->em->refresh($sale);
        $this->assertSame(CommunicationSaleInfo::STATE_PROCESS_AWAITING_PROVIDER, $sale->getStateProcess());
        $this->assertNull($sale->getProvider());
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
