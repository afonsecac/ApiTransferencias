<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderResolver;
use App\Service\CommunicationSaleService;

/**
 * @covers \App\Service\CommunicationSaleService
 *
 * Fase 3, contra Postgres real (no mocks): el guard de admisión introducido
 * en resolveAndGuardProvider() debe leer el proveedor REAL desde la cadena
 * de relaciones Doctrine package -> priceClientPackage -> product, y
 * validarlo contra las filas REALES de client_provider_routing a través del
 * repositorio real (no un doble). Esto es justo lo que
 * CommunicationSaleServiceProviderGuardTest (unitario, con mocks) no puede
 * verificar: que la consulta SQL de allowedForClient() y la travesía de
 * relaciones del producto funcionan de verdad.
 */
class CommunicationSaleServiceProviderGuardFunctionalTest extends ProviderFunctionalTestCase
{
    private function saleService(): CommunicationSaleService
    {
        return self::getContainer()->get(CommunicationSaleService::class);
    }

    private function rechargeRequest(int $packageId): CommunicationSaleRecharge
    {
        static $counter = 0;
        $counter++;

        return (new CommunicationSaleRecharge())
            ->setPackageId($packageId)
            ->setClientTransactionId('functional-guard-' . $counter)
            ->setPhoneNumber('5550000000');
    }

    public function testRejectsRechargeWhenProductProviderNotAllowedForClient(): void
    {
        // Sin filas de client_provider_routing y con el kill switch en su
        // valor sembrado ('0' = ignorar la tabla), allowedForClient() solo
        // permite ETECSA. Un producto DTONE debe rechazarse con 409.
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::DTONE->value);

        $this->authenticateAs($account);

        try {
            $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));
            $this->fail('Se esperaba MyCurrentException por proveedor no habilitado.');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame(
                'El paquete pertenece a un proveedor no habilitado para este cliente',
                $e->getMessage(),
            );
        }
    }

    public function testAdmitsRechargeWhenProductProviderMatchesDefault(): void
    {
        $this->setSysConfig('communications.dispatch.enabled', '0');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        // Amount 0: hasAvailableBalance() sin ledger seeded (0 disponible)
        // sigue admitiendo una venta de importe 0 — evita tener que sembrar
        // BalanceOperation solo para probar el guard de proveedor.
        $package = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::ETECSA->value, amount: 0.0);

        $this->authenticateAs($account);

        $result = $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));

        $this->assertNotNull($result);
        $this->assertSame(CommunicationProviderEnum::ETECSA->value, $result->getProvider());
        $this->assertNotNull($result->getId());
    }

    public function testAdmitsDtoneProductWhenClientHasActiveDtoneRouting(): void
    {
        $this->setSysConfig('communications.dispatch.enabled', '0');
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '1');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::DTONE->value, amount: 0.0);

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value);

        $this->authenticateAs($account);

        $result = $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));

        $this->assertNotNull($result);
        $this->assertSame(CommunicationProviderEnum::DTONE->value, $result->getProvider());
    }
}
