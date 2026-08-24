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
 * Contra Postgres real (no mocks): el guard de qué proveedor despacha una
 * venta vive en ProviderDispatchResolver::select() (ver su propia suite
 * unitaria, ProviderDispatchResolverTest) — CommunicationSaleService::admitV2()
 * solo delega. Esto verifica el camino end-to-end real: la consulta SQL de
 * ClientProviderRoutingRepository y el binding CommunicationPackageProviderProduct
 * funcionan de verdad, no solo contra dobles.
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
        // valor sembrado ('0' = ignorar la tabla), candidateProviders()
        // solo prueba ETECSA. El paquete solo tiene vínculo a DTONE — ningún
        // proveedor candidato puede despacharlo, PACKAGE_NOT_DISPATCHABLE.
        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, CommunicationProviderEnum::DTONE->value);

        $this->authenticateAs($account);

        try {
            $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));
            $this->fail('Se esperaba MyCurrentException por proveedor no despachable.');
        } catch (MyCurrentException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('PACKAGE_NOT_DISPATCHABLE', $e->getCodeWork());
        }
    }

    public function testAdmitsRechargeWhenProductProviderMatchesDefault(): void
    {
        // canDispatchTo() (que ProviderDispatchResolver::select() exige de
        // cada candidato en admisión, no solo al despachar) requiere
        // credenciales configuradas — sin esto ningún proveedor es
        // candidato viable, sin importar el binding del paquete.
        $this->setSysConfig('provider.etecsa.test.base_url', 'https://etecsa.example.test');
        $this->setSysConfig('provider.etecsa.test.api_key', 'functional-key');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        // Precio 0: hasAvailableBalance() sin ledger seeded (0 disponible)
        // sigue admitiendo una venta de importe 0 — evita tener que sembrar
        // BalanceOperation solo para probar el guard de proveedor.
        $package = $this->createSellableV2Package($environment, CommunicationProviderEnum::ETECSA->value, price: 0.0);

        $this->authenticateAs($account);

        $result = $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));

        $this->assertNotNull($result);
        $this->assertSame(CommunicationProviderEnum::ETECSA->value, $result->getProvider());
        $this->assertNotNull($result->getId());
    }

    public function testAdmitsDtoneProductWhenClientHasActiveDtoneRouting(): void
    {
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '1');
        $this->setSysConfig('provider.dtone.test.base_url', 'https://dtone.example.test');
        $this->setSysConfig('provider.dtone.test.api_key', 'functional-key');
        $this->setSysConfig('provider.dtone.test.api_secret', 'functional-secret');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        $package = $this->createSellableV2Package($environment, CommunicationProviderEnum::DTONE->value, price: 0.0);

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value);

        $this->authenticateAs($account);

        $result = $this->saleService()->processRecharge($this->rechargeRequest($package->getId()));

        $this->assertNotNull($result);
        $this->assertSame(CommunicationProviderEnum::DTONE->value, $result->getProvider());
    }
}
