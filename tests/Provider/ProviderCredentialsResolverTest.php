<?php

namespace App\Tests\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\DTOne\DTOneCommunicationProvider;
use App\Provider\DTOne\DTOneHttpClient;
use App\Provider\DTOne\DTOneStatusMapper;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\SysConfigRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Provider\ProviderCredentialsResolver
 */
class ProviderCredentialsResolverTest extends TestCase
{
    /**
     * Usa el adaptador DTOne real (esquema base_url/api_key/api_secret,
     * los 3 obligatorios) — DTOneStatusMapper es `final`, se instancia real.
     */
    private function registryWithDtone(): ProviderRegistry
    {
        return new ProviderRegistry([
            new DTOneCommunicationProvider(
                $this->createMock(DTOneHttpClient::class),
                new DTOneStatusMapper(),
                new NullLogger(),
            ),
        ]);
    }

    public function testResolvesKeysAccordingToProviderSchema(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.prod.base_url', true, 'https://dvs-api.dtone.com'],
            ['provider.dtone.prod.api_key', true, 'the-key'],
            ['provider.dtone.prod.api_secret', true, 'the-secret'],
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());
        $values = $resolver->get(CommunicationProviderEnum::DTONE, 'PROD');

        $this->assertSame([
            'base_url' => 'https://dvs-api.dtone.com',
            'api_key' => 'the-key',
            'api_secret' => 'the-secret',
        ], $values);
    }

    public function testLowercasesProviderAndEnvironmentInTheKey(): void
    {
        $seenKeys = [];
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnCallback(function (string $key) use (&$seenKeys) {
            $seenKeys[] = $key;

            return null;
        });

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());
        $resolver->get(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertContains('provider.dtone.test.base_url', $seenKeys);
        $this->assertContains('provider.dtone.test.api_key', $seenKeys);
        $this->assertContains('provider.dtone.test.api_secret', $seenKeys);
    }

    public function testReturnsNullFieldsWhenNothingConfigured(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturn(null);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());
        $values = $resolver->get(CommunicationProviderEnum::DTONE, 'PROD');

        $this->assertNull($values['base_url']);
        $this->assertNull($values['api_key']);
        $this->assertNull($values['api_secret']);
    }

    // ---- isFullyConfigured ----

    public function testIsFullyConfiguredReturnsFalseWhenARequiredFieldIsMissing(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.test.base_url', true, 'https://sandbox.example'],
            ['provider.dtone.test.api_key', true, 'the-key'],
            ['provider.dtone.test.api_secret', true, null], // falta
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertFalse($resolver->isFullyConfigured(CommunicationProviderEnum::DTONE, 'TEST'));
    }

    public function testIsFullyConfiguredReturnsTrueWhenAllRequiredFieldsArePresent(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.test.base_url', true, 'https://sandbox.example'],
            ['provider.dtone.test.api_key', true, 'the-key'],
            ['provider.dtone.test.api_secret', true, 'the-secret'],
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertTrue($resolver->isFullyConfigured(CommunicationProviderEnum::DTONE, 'TEST'));
    }

    public function testIsFullyConfiguredIgnoresAbsenceOfNonRequiredFields(): void
    {
        // Proveedor sintético con un campo opcional, para cubrir el caso —
        // hoy ningún proveedor real tiene campos required:false salvo esto.
        $fakeProvider = new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::ETECSA;
            }

            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::RECHARGE];
            }

            public function getConfigSchema(): array
            {
                return [
                    new ProviderConfigField('api_key', 'API key', required: true, secret: true),
                    new ProviderConfigField('extra_flag', 'Bandera opcional', required: false, secret: false),
                ];
            }
        };

        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.etecsa.test.api_key', true, 'the-key'],
            ['provider.etecsa.test.extra_flag', true, null], // ausente, pero no es obligatorio
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, new ProviderRegistry([$fakeProvider]));

        $this->assertTrue($resolver->isFullyConfigured(CommunicationProviderEnum::ETECSA, 'TEST'));
    }

    // ---- isActive / isEnabled ----

    public function testIsActiveDefaultsToTrueWhenNeverSet(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturn(null);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertTrue($resolver->isActive(CommunicationProviderEnum::DTONE, 'PROD'));
    }

    public function testIsActiveReturnsFalseWhenExplicitlyDeactivated(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.prod.active', true, '0'],
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertFalse($resolver->isActive(CommunicationProviderEnum::DTONE, 'PROD'));
    }

    public function testIsActiveReturnsTrueWhenExplicitlyReactivated(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.prod.active', true, '1'],
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertTrue($resolver->isActive(CommunicationProviderEnum::DTONE, 'PROD'));
    }

    public function testIsEnabledRequiresBothConfiguredAndActive(): void
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.test.base_url', true, 'https://sandbox.example'],
            ['provider.dtone.test.api_key', true, 'the-key'],
            ['provider.dtone.test.api_secret', true, 'the-secret'],
            ['provider.dtone.test.active', true, '0'], // completo pero desactivado a mano
        ]);

        $resolver = new ProviderCredentialsResolver($sysConfigRepo, $this->registryWithDtone());

        $this->assertTrue($resolver->isFullyConfigured(CommunicationProviderEnum::DTONE, 'TEST'));
        $this->assertFalse($resolver->isActive(CommunicationProviderEnum::DTONE, 'TEST'));
        $this->assertFalse($resolver->isEnabled(CommunicationProviderEnum::DTONE, 'TEST'));
    }
}
