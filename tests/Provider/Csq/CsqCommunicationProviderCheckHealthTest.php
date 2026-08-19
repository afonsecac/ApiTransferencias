<?php

namespace App\Tests\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Provider\Csq\CsqCommunicationProvider::checkHealth
 */
class CsqCommunicationProviderCheckHealthTest extends TestCase
{
    private CsqHttpClient&MockObject $client;
    private CsqCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CsqHttpClient::class);
        $this->provider = new CsqCommunicationProvider($this->client, new \App\Provider\Csq\CsqStatusMapper(), new NullLogger());
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(provider: CommunicationProviderEnum::CSQ, environmentType: 'TEST');
    }

    public function testAvailableWhenPingSucceeds(): void
    {
        $this->client->expects($this->once())->method('ping')->with($this->context())->willReturn(['rc' => 0]);

        $result = $this->provider->checkHealth($this->context());

        $this->assertTrue($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertIsInt($result->latencyMs);
    }

    public function testUnavailableWhenPingThrows(): void
    {
        $this->client->method('ping')->willThrowException(new MyCurrentException('CSQ_REQUEST_FAILED', 'boom', 502));

        $result = $this->provider->checkHealth($this->context());

        $this->assertFalse($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertSame('boom', $result->error);
    }
}
