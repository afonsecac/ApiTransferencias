<?php

namespace App\Tests\Functional\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Provider\ProviderResolver;

/**
 * @covers \App\Repository\ClientProviderRoutingRepository
 * @covers \App\Provider\ProviderResolver
 *
 * Verifica contra Postgres real la precedencia SQL de
 * ClientProviderRoutingRepository::findResolvedFor(), de más a menos
 * específico: (cliente, entorno, tipo) > (cliente, entorno) > (cliente,
 * tipo) > (cliente). ProviderRoutingAdminServiceTest (unitario) mockea el
 * repositorio entero, así que nunca ejercita esta query real.
 */
class ProviderResolverPrecedenceFunctionalTest extends ProviderFunctionalTestCase
{
    private function resolver(): ProviderResolver
    {
        return self::getContainer()->get(ProviderResolver::class);
    }

    public function testPrecedenceFromLeastToMostSpecific(): void
    {
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '1');

        $client = $this->createClient();
        $environment = $this->createEnvironment();
        $otherEnvironment = $this->createEnvironment();

        // (cliente) — fallback general del cliente.
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value);

        // (cliente, entorno) — más específico que el anterior para $environment.
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, $environment);

        // (cliente, entorno, tipo=RE) — el más específico posible, solo para recargas.
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, $environment, 'RE');

        // Un entorno sin routing propio cae al fallback (cliente) general.
        $this->assertSame(
            CommunicationProviderEnum::ETECSA,
            $this->resolver()->resolveEffectiveFor($client->getId(), $otherEnvironment->getId(), null),
        );

        // (cliente, entorno) sin tipo de venta usa la fila de entorno (DTONE).
        $this->assertSame(
            CommunicationProviderEnum::DTONE,
            $this->resolver()->resolveEffectiveFor($client->getId(), $environment->getId(), null),
        );

        // (cliente, entorno, tipo=RE) usa la fila más específica (ETECSA),
        // aunque la fila de entorno sin tipo diga DTONE.
        $this->assertSame(
            CommunicationProviderEnum::ETECSA,
            $this->resolver()->resolveEffectiveFor($client->getId(), $environment->getId(), 'RE'),
        );

        // (cliente, entorno, tipo=SA) no matchea la fila 'RE': cae a la fila
        // de entorno sin tipo (DTONE), no al fallback general.
        $this->assertSame(
            CommunicationProviderEnum::DTONE,
            $this->resolver()->resolveEffectiveFor($client->getId(), $environment->getId(), 'SA'),
        );
    }

    public function testKillSwitchIgnoresRoutingTableEntirely(): void
    {
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '0');

        $client = $this->createClient();
        $environment = $this->createEnvironment();

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, $environment);

        // Con el kill switch en '0', se ignora la tabla y siempre se
        // devuelve el proveedor por defecto (ETECSA, sembrado en
        // communications.provider.default), aunque exista una fila DTONE.
        $this->assertSame(
            CommunicationProviderEnum::ETECSA,
            $this->resolver()->resolveEffectiveFor($client->getId(), $environment->getId(), null),
        );
    }

    public function testAllowedForClientReturnsOnlyActiveDistinctProviders(): void
    {
        $this->setSysConfig(ProviderResolver::ROUTING_ENABLED_KEY, '1');

        $client = $this->createClient();
        $environment = $this->createEnvironment();

        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, $environment);
        $this->createRouting($client, CommunicationProviderEnum::ETECSA->value, $environment, 'RE');
        // Fila inactiva: no debe contarse.
        $this->createRouting($client, CommunicationProviderEnum::DTONE->value, null, 'SA', isActive: false);

        $allowed = $this->resolver()->allowedForClient($client, $environment->getId());

        $this->assertCount(2, $allowed);
        $this->assertContains(CommunicationProviderEnum::DTONE, $allowed);
        $this->assertContains(CommunicationProviderEnum::ETECSA, $allowed);
    }
}
