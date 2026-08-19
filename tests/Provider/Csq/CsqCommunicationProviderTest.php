<?php

namespace App\Tests\Provider\Csq;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Provider\Contract\ProviderContext;
use App\Provider\Csq\CsqCommunicationProvider;
use App\Provider\Csq\CsqHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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
        $this->provider = new CsqCommunicationProvider($this->client, new \App\Provider\Csq\CsqStatusMapper(), new NullLogger());
    }

    public function testGetCodeReturnsCsq(): void
    {
        $this->assertSame(CommunicationProviderEnum::CSQ, $this->provider->getCode());
    }

    public function testGetCapabilitiesIncludesCatalogRechargeAndBalance(): void
    {
        $this->assertSame(
            [ProviderCapabilityEnum::CATALOG, ProviderCapabilityEnum::RECHARGE, ProviderCapabilityEnum::BALANCE],
            $this->provider->getCapabilities(),
        );
    }

    public function testGetConfigSchemaIncludesTerminal(): void
    {
        $keys = array_map(static fn ($field) => $field->key, $this->provider->getConfigSchema());

        $this->assertContains('terminal', $keys);
    }

    public function testTerminalIsRequiredAndNotSecret(): void
    {
        $terminal = array_values(array_filter(
            $this->provider->getConfigSchema(),
            static fn ($field) => $field->key === 'terminal',
        ))[0];

        $this->assertTrue($terminal->required);
        $this->assertFalse($terminal->secret);
    }

    public function testDefaultReceiverEmailAndDocumentNumberAreRequiredAndNotSecret(): void
    {
        // Confirmado en vivo el 2026-08-10/11: sin estos dos campos en el
        // body de Purchase, CSQ rechaza con resultcode 991 — ver
        // CsqHttpClient::purchase().
        $schema = $this->provider->getConfigSchema();

        foreach (['default_receiver_email', 'default_document_number'] as $key) {
            $field = array_values(array_filter($schema, static fn ($f) => $f->key === $key))[0] ?? null;

            $this->assertNotNull($field, "Falta el campo {$key} en getConfigSchema()");
            $this->assertTrue($field->required);
            $this->assertFalse($field->secret);
        }
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
