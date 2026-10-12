<?php

namespace App\Tests\Provider\Own;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderDomainEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\Etecsa\EtecsaCommunicationProvider;
use App\Provider\Own\OwnMarketProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Own\OwnMarketProvider
 */
class OwnMarketProviderTest extends TestCase
{
    public function testItIsTheOwnProviderAndOperatesOnlyInTheMarketDomain(): void
    {
        $provider = new OwnMarketProvider();

        $this->assertSame(CommunicationProviderEnum::OWN, $provider->getCode());
        $this->assertSame([ProviderDomainEnum::Market], $provider->getDomains());
    }

    public function testItNeedsNoConfigurationAndHasNoCommunicationsCapabilities(): void
    {
        $provider = new OwnMarketProvider();

        $this->assertSame([], $provider->getConfigSchema(), 'Esquema vacío: isFullyConfigured() siempre da true.');
        $this->assertSame([], $provider->getCapabilities());
    }

    public function testItsHealthCheckIsTrivialSoItNeverRaisesAlertsIfItEntersThePing(): void
    {
        $result = (new OwnMarketProvider())->checkHealth(new ProviderContext(provider: CommunicationProviderEnum::OWN, environmentType: 'TEST'));

        $this->assertTrue($result->available);
        $this->assertFalse($result->inconclusive, 'Un adaptador sin HealthCheck daría «inconclusive» y avisaría a los administradores en cada ciclo.');
    }

    /**
     * El enum es la fuente del dominio de cada código; cada adaptador lo repite. Si discrepan, uno de los dos miente.
     *
     * @dataProvider adapters
     *
     * @param class-string<\App\Provider\Contract\ProviderDomainAwareInterface> $adapter
     */
    public function testEveryAdapterDeclaresTheSameDomainsAsItsEnumCase(string $adapter): void
    {
        $instance = (new \ReflectionClass($adapter))->newInstanceWithoutConstructor();

        $this->assertSame($instance->getCode()->domains(), $instance->getDomains());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function adapters(): iterable
    {
        yield 'ETECSA' => [EtecsaCommunicationProvider::class];
        yield 'DTone' => [DTOneCommunicationProvider::class];
        yield 'CSQ' => [CsqCommunicationProvider::class];
        yield 'OWN' => [OwnMarketProvider::class];
    }
}
