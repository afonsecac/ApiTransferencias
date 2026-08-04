<?php

namespace App\Tests\Provider\Etecsa;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\Etecsa\EtecsaCommunicationProvider;
use App\Provider\Etecsa\EtecsaStatusMapper;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Etecsa\EtecsaCommunicationProvider::checkHealth
 */
class EtecsaCommunicationProviderCheckHealthTest extends TestCase
{
    private EtecsaGatewayClient&MockObject $client;
    private EtecsaCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(EtecsaGatewayClient::class);

        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $environmentRepository->method('findOneBy')->willReturn((new Environment())->setType('TEST'));

        // EtecsaStatusMapper es `final` y checkHealth() no la usa: se
        // instancia real en vez de doblarla.
        $this->provider = new EtecsaCommunicationProvider(
            $this->client,
            new EtecsaStatusMapper(),
            $environmentRepository,
        );
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::ETECSA, environmentType: 'TEST');
    }

    public function testMaps200ToAvailable(): void
    {
        $this->client->method('ping')->willReturn(['status' => 200, 'body' => ['available' => true, 'latencyMs' => 88]]);

        $result = $this->provider->checkHealth($this->context());

        $this->assertTrue($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertSame(88, $result->latencyMs);
    }

    public function testMaps503ToUnavailable(): void
    {
        $this->client->method('ping')->willReturn(['status' => 503, 'body' => ['available' => false, 'error' => 'breaker open']]);

        $result = $this->provider->checkHealth($this->context());

        $this->assertFalse($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertSame('breaker open', $result->error);
    }

    public function testMaps403ToInconclusive(): void
    {
        $this->client->method('ping')->willReturn(['status' => 403, 'body' => ['error' => 'missing scope']]);

        $result = $this->provider->checkHealth($this->context());

        $this->assertTrue($result->inconclusive);
        $this->assertFalse($result->available);
    }

    public function testTransportExceptionMapsToUnavailable(): void
    {
        $this->client->method('ping')->willThrowException(new MyCurrentException('ETECSA_GATEWAY_TIMEOUT', 'timeout', 503));

        $result = $this->provider->checkHealth($this->context());

        $this->assertFalse($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertSame('timeout', $result->error);
    }
}
