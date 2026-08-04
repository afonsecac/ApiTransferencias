<?php

namespace App\Tests\Functional\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderActionTypeEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderPingResult;
use App\Repository\ProviderAvailabilityRepository;
use App\Service\Provider\ProviderAvailabilityService;

/**
 * @covers \App\Service\Provider\ProviderAvailabilityService
 *
 * Contra Postgres real: verifica que el AND de tres condiciones (global,
 * credenciales+manual, auto del ping) y la auditoría de setManual() escriben
 * y leen de verdad, no solo en dobles (ver ProviderAvailabilityServiceTest,
 * unitario, para la matriz de casos completa con mocks).
 */
class ProviderAvailabilityServiceFunctionalTest extends ProviderFunctionalTestCase
{
    private function service(): ProviderAvailabilityService
    {
        return self::getContainer()->get(ProviderAvailabilityService::class);
    }

    private function availabilityRepo(): ProviderAvailabilityRepository
    {
        return self::getContainer()->get(ProviderAvailabilityRepository::class);
    }

    private function configureEtecsaTest(): void
    {
        $this->setSysConfig('provider.etecsa.test.base_url', 'https://etecsa.example.test');
        $this->setSysConfig('provider.etecsa.test.api_key', 'functional-key');
    }

    public function testSetManualRejectsActivatingUnconfiguredProvider(): void
    {
        // Fuerza explícitamente "sin configurar" (en vez de confiar en la
        // ausencia de la fila): cache.sys_config es un caché de proceso, no
        // ligado a la transacción/rollback de cada test, así que un valor
        // cacheado por otro test de esta clase podría sobrevivir aquí.
        $this->setSysConfig('provider.etecsa.test.base_url', '');
        $this->setSysConfig('provider.etecsa.test.api_key', '');

        $admin = $this->createAdminUser();
        $this->authenticateAsAdmin($admin);

        try {
            $this->service()->setManual(CommunicationProviderEnum::ETECSA, 'TEST', true);
            $this->fail('Se esperaba MyCurrentException por proveedor no configurado.');
        } catch (MyCurrentException $e) {
            $this->assertSame('PROVIDER_NOT_CONFIGURED', $e->getCodeWork());
            $this->assertSame(409, $e->getCode());
        }
    }

    public function testSetManualEnablePersistsAuditedRow(): void
    {
        $this->configureEtecsaTest();
        $admin = $this->createAdminUser();
        $this->authenticateAsAdmin($admin);

        $this->service()->setManual(CommunicationProviderEnum::ETECSA, 'TEST', true, 'reactivación funcional');

        $row = $this->availabilityRepo()->findOneByProviderAndType('ETECSA', 'TEST');

        $this->assertNotNull($row);
        $this->assertTrue($row->isAutoEnabled());
        $this->assertSame(ProviderActionTypeEnum::MANUAL, $row->getLastActionType());
        $this->assertSame($admin->getId(), $row->getLastActionBy()?->getId());
        $this->assertSame('reactivación funcional', $row->getLastActionReason());

        $this->assertTrue($this->service()->canDispatchTo('ETECSA', 'TEST'));
    }

    public function testCanDispatchToFalseWhenManuallyDisabled(): void
    {
        $this->configureEtecsaTest();
        $admin = $this->createAdminUser();
        $this->authenticateAsAdmin($admin);

        $this->service()->setManual(CommunicationProviderEnum::ETECSA, 'TEST', false);

        $this->assertFalse($this->service()->canDispatchTo('ETECSA', 'TEST'));
    }

    public function testRecordPingFailureDisablesAutoAndBlocksDispatch(): void
    {
        $this->configureEtecsaTest();

        $this->service()->recordPing(CommunicationProviderEnum::ETECSA, 'TEST', ProviderPingResult::unavailable('timeout'));

        $row = $this->availabilityRepo()->findOneByProviderAndType('ETECSA', 'TEST');
        $this->assertNotNull($row);
        $this->assertFalse($row->isAutoEnabled());
        $this->assertSame(ProviderActionTypeEnum::AUTO, $row->getLastActionType());
        $this->assertFalse($this->service()->canDispatchTo('ETECSA', 'TEST'));
    }

    public function testRecordPingRecoveryReturnsTrueOnlyWhenAlsoManuallyEnabled(): void
    {
        $this->configureEtecsaTest();

        // Cae por ping...
        $this->service()->recordPing(CommunicationProviderEnum::ETECSA, 'TEST', ProviderPingResult::unavailable('timeout'));
        $this->assertFalse($this->service()->canDispatchTo('ETECSA', 'TEST'));

        // ...y se recupera: con MANUAL en su default (activo), sí queda despachable.
        $justRecovered = $this->service()->recordPing(CommunicationProviderEnum::ETECSA, 'TEST', ProviderPingResult::available(50));

        $this->assertTrue($justRecovered);
        $this->assertTrue($this->service()->canDispatchTo('ETECSA', 'TEST'));
    }
}
