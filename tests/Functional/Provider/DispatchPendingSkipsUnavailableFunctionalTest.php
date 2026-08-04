<?php

namespace App\Tests\Functional\Provider;

use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationProviderEnum;
use App\Provider\ProviderResolver;
use App\Service\CommunicationSaleService;
use App\Service\CommunicationsDispatchService;

/**
 * @covers \App\Service\CommunicationsDispatchService::dispatchPending
 *
 * Contra Postgres real: de dos ventas admitidas y diferidas (dispatch global
 * apagado al crearlas), dispatchPending() debe encolar solo la del proveedor
 * despachable y reportar la otra en `skipped`, sin tocar su estado.
 */
class DispatchPendingSkipsUnavailableFunctionalTest extends ProviderFunctionalTestCase
{
    private function saleService(): CommunicationSaleService
    {
        return self::getContainer()->get(CommunicationSaleService::class);
    }

    private function dispatchService(): CommunicationsDispatchService
    {
        return self::getContainer()->get(CommunicationsDispatchService::class);
    }

    private function rechargeRequest(int $packageId, string $suffix): CommunicationSaleRecharge
    {
        return (new CommunicationSaleRecharge())
            ->setPackageId($packageId)
            ->setClientTransactionId('functional-dispatch-skip-' . $suffix)
            ->setPhoneNumber('5550000000');
    }

    public function testSkipsSaleOfUnconfiguredProviderAndDispatchesTheOther(): void
    {
        // Credenciales de ETECSA completas — despachable. DTOne se deja sin
        // configurar a propósito: ProviderCredentialsResolver::isFullyConfigured()
        // debe dar false y dispatchPending() debe saltarla (requisito: no
        // despachar hacia un proveedor mal configurado).
        $this->setSysConfig('provider.etecsa.test.base_url', 'https://etecsa.example.test');
        $this->setSysConfig('provider.etecsa.test.api_key', 'functional-key');
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '1');

        // Dispatch global apagado al admitir: ambas ventas quedan PENDING/CREATED
        // sin encolar, que es justo el estado que dispatchPending() recorre.
        $this->setSysConfig('communications.dispatch.enabled', '0');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $account = $this->createAccount($client, $environment);
        // uniq_cpr_scope es único por (client, environment, saleType) —
        // saleType distinto solo para poder tener dos filas activas para el
        // mismo cliente sin chocar; findActiveProviderCodesForClient() no
        // filtra por scope, así que ambas cuentan igual como "permitido".
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, saleType: 'recharge');

        $etecsaPackage = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::ETECSA->value, amount: 0.0);
        $dtonePackage = $this->createSellablePackage($environment, $account, CommunicationProviderEnum::DTONE->value, amount: 0.0);

        $this->authenticateAs($account);

        $etecsaSale = $this->saleService()->processRecharge($this->rechargeRequest($etecsaPackage->getId(), 'etecsa'));
        $dtoneSale = $this->saleService()->processRecharge($this->rechargeRequest($dtonePackage->getId(), 'dtone'));

        $this->setSysConfig('communications.dispatch.enabled', '1');

        $result = $this->dispatchService()->dispatchPending();

        $this->assertSame(1, $result['recharges']);
        $this->assertSame(1, $result['skipped']['DTONE|TEST'] ?? null);
        $this->assertArrayNotHasKey('ETECSA|TEST', $result['skipped']);

        $this->em->refresh($dtoneSale);
        $this->assertSame(\App\Enums\CommunicationStateEnum::CREATED->value, $dtoneSale->getStateProcess());
    }
}
