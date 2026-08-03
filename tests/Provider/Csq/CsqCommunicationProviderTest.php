<?php

namespace App\Tests\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Provider\Csq\CsqCommunicationProvider
 */
class CsqCommunicationProviderTest extends TestCase
{
    private CsqHttpClient&MockObject $client;
    private CsqCommunicationProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(CsqHttpClient::class);
        $this->provider = new CsqCommunicationProvider($this->client);
    }

    public function testGetCodeReturnsCsq(): void
    {
        $this->assertSame(CommunicationProviderEnum::CSQ, $this->provider->getCode());
    }

    public function testGetCapabilitiesIsEmptyUntilBusinessMethodsAreSpecified(): void
    {
        $this->assertSame([], $this->provider->getCapabilities());
    }

    public function testPingDelegatesToHttpClient(): void
    {
        $context = new ProviderContext(provider: CommunicationProviderEnum::CSQ, environmentType: 'TEST');
        $this->client->expects($this->once())
            ->method('ping')
            ->with($context, 'hola')
            ->willReturn(['rc' => 0, 'echo' => 'hola', 'ts' => 1]);

        $result = $this->provider->ping($context, 'hola');

        $this->assertSame(['rc' => 0, 'echo' => 'hola', 'ts' => 1], $result);
    }
}
