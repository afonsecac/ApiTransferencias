<?php

namespace App\Tests\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderDomainEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderDomainAwareInterface;
use App\Provider\Contract\RechargeProviderInterface;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\Etecsa\EtecsaCommunicationProvider;
use App\Provider\ProviderRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * COMR-63 (C2, ADR-001): un proveedor declara en qué dominios opera (comunicaciones, mercado). registered()
 * conserva su significado de siempre (solo los de comunicaciones: ping, disponibilidad, vínculos y enrutado
 * lo usan), y registeredFor(dominio) lista los de otro dominio sin que aparezcan en esas pantallas y tareas.
 *
 * @covers \App\Provider\ProviderRegistry
 */
class ProviderRegistryDomainsTest extends TestCase
{
    private function provider(CommunicationProviderEnum $code): CommunicationProviderInterface&MockObject
    {
        $provider = $this->createMock(CommunicationProviderInterface::class);
        $provider->method('getCode')->willReturn($code);

        return $provider;
    }

    /**
     * @param list<ProviderDomainEnum> $domains
     */
    private function providerWithDomains(CommunicationProviderEnum $code, array $domains): ProviderDomainAwareInterface&MockObject
    {
        $provider = $this->createMock(ProviderDomainAwareInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('getDomains')->willReturn($domains);

        return $provider;
    }

    public function testAProviderThatDeclaresNoDomainCountsAsCommunications(): void
    {
        $registry = new ProviderRegistry([$this->provider(CommunicationProviderEnum::ETECSA)]);

        $this->assertSame([CommunicationProviderEnum::ETECSA], $registry->registered());
        $this->assertSame([CommunicationProviderEnum::ETECSA], $registry->registeredFor(ProviderDomainEnum::Communications));
        $this->assertSame([], $registry->registeredFor(ProviderDomainEnum::Market));
    }

    public function testAMarketOnlyProviderIsNotInRegisteredButIsInRegisteredForMarket(): void
    {
        $registry = new ProviderRegistry([
            $this->provider(CommunicationProviderEnum::ETECSA),
            $this->providerWithDomains(CommunicationProviderEnum::DTONE, [ProviderDomainEnum::Market]),
        ]);

        $this->assertSame([CommunicationProviderEnum::ETECSA], $registry->registered());
        $this->assertSame([CommunicationProviderEnum::DTONE], $registry->registeredFor(ProviderDomainEnum::Market));
    }

    public function testAMarketOnlyProviderCanStillBeResolvedByCode(): void
    {
        $market = $this->providerWithDomains(CommunicationProviderEnum::DTONE, [ProviderDomainEnum::Market]);
        $registry = new ProviderRegistry([$market]);

        $this->assertTrue($registry->has(CommunicationProviderEnum::DTONE));
        $this->assertSame($market, $registry->get(CommunicationProviderEnum::DTONE));
    }

    public function testAProviderInBothDomainsAppearsInBoth(): void
    {
        $registry = new ProviderRegistry([
            $this->providerWithDomains(CommunicationProviderEnum::CSQ, [ProviderDomainEnum::Communications, ProviderDomainEnum::Market]),
        ]);

        $this->assertSame([CommunicationProviderEnum::CSQ], $registry->registered());
        $this->assertSame([CommunicationProviderEnum::CSQ], $registry->registeredFor(ProviderDomainEnum::Market));
    }

    public function testAllImplementingIsNotFilteredByDomain(): void
    {
        $recharge = $this->createMock(RechargeProviderInterface::class);
        $recharge->method('getCode')->willReturn(CommunicationProviderEnum::ETECSA);
        $registry = new ProviderRegistry([$recharge, $this->providerWithDomains(CommunicationProviderEnum::DTONE, [ProviderDomainEnum::Market])]);

        $this->assertSame([$recharge], $registry->allImplementing(RechargeProviderInterface::class));
    }

    /**
     * @dataProvider realAdapters
     *
     * @param class-string<ProviderDomainAwareInterface> $adapter
     */
    public function testTheRealAdaptersDeclareTheCommunicationsDomainExplicitly(string $adapter): void
    {
        $this->assertTrue(is_subclass_of($adapter, ProviderDomainAwareInterface::class), $adapter . ' debe declarar su dominio.');

        // getDomains() no usa estado: se evita construir el cliente HTTP real.
        $instance = (new \ReflectionClass($adapter))->newInstanceWithoutConstructor();

        $this->assertSame([ProviderDomainEnum::Communications], $instance->getDomains());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function realAdapters(): iterable
    {
        yield 'ETECSA' => [EtecsaCommunicationProvider::class];
        yield 'DTone' => [DTOneCommunicationProvider::class];
        yield 'CSQ' => [CsqCommunicationProvider::class];
    }
}
