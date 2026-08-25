<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationsDispatchService;

/**
 * @covers \App\Service\CommunicationsDispatchService::dispatchPending
 *
 * Contra Postgres real: de dos ventas ya admitidas y persistidas en estado
 * PENDING/CREATED (el estado que deja una venta esperando despacho —
 * ver CommunicationSaleService::dispatchOrDefer()), dispatchPending() debe
 * encolar solo la del proveedor despachable y reportar la otra en
 * `skipped`, sin tocar su estado.
 *
 * Construye las filas directamente (sin pasar por
 * CommunicationSaleService::processRecharge()) porque lo que este test
 * cubre es dispatchPending() en sí — el guard de qué proveedor puede
 * ADMITIRSE (ProviderDispatchResolver::select(), que ya exige
 * canDispatchTo() por candidato) es un tema aparte, cubierto en
 * CommunicationSaleServiceProviderGuardFunctionalTest.
 */
class DispatchPendingSkipsUnavailableFunctionalTest extends ProviderFunctionalTestCase
{
    private function dispatchService(): CommunicationsDispatchService
    {
        return self::getContainer()->get(CommunicationsDispatchService::class);
    }

    private function pendingRecharge(\App\Entity\Account $account, string $provider, string $suffix): CommunicationSaleRecharge
    {
        $recharge = (new CommunicationSaleRecharge())
            ->setTenant($account)
            ->setProvider($provider)
            ->setPackageId(1)
            ->setAmount(0.0)
            ->setCurrency('USD')
            ->setClientTransactionId('functional-dispatch-skip-' . $suffix)
            ->setTransactionId('26081001' . $suffix)
            ->setPhoneNumber('5550000000')
            ->setState(CommunicationStateEnum::PENDING)
            ->setStateProcess(CommunicationStateEnum::CREATED->value);

        $this->em->persist($recharge);

        return $recharge;
    }

    public function testSkipsSaleOfUnconfiguredProviderAndDispatchesTheOther(): void
    {
        // Credenciales de ETECSA completas — despachable. DTOne se deja sin
        // configurar a propósito: ProviderCredentialsResolver::isFullyConfigured()
        // debe dar false y dispatchPending() debe saltarla (requisito: no
        // despachar hacia un proveedor mal configurado).
        $this->setSysConfig('provider.etecsa.test.base_url', 'https://etecsa.example.test');
        $this->setSysConfig('provider.etecsa.test.api_key', 'functional-key');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);

        $etecsaSale = $this->pendingRecharge($account, CommunicationProviderEnum::ETECSA->value, '00001');
        $dtoneSale = $this->pendingRecharge($account, CommunicationProviderEnum::DTONE->value, '00002');
        $this->em->flush();

        $result = $this->dispatchService()->dispatchPending();

        $this->assertSame(1, $result['recharges']);
        $this->assertSame(1, $result['skipped']['DTONE|TEST'] ?? null);
        $this->assertArrayNotHasKey('ETECSA|TEST', $result['skipped']);

        $this->em->refresh($dtoneSale);
        $this->assertSame(CommunicationStateEnum::CREATED->value, $dtoneSale->getStateProcess());
    }
}
