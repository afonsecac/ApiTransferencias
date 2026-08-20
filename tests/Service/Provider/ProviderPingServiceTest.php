<?php

namespace App\Tests\Service\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderBalanceResult;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderHealthCheckInterface;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderPingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Service\Provider\ProviderPingService
 */
class ProviderPingServiceTest extends TestCase
{
    private function contextFactory(): ProviderContextFactory
    {
        // ProviderResolver y ProviderContextFactory son `final`: se
        // instancian reales con dependencias mockeadas — forEnvironmentType()
        // no usa el resolver en absoluto.
        $resolver = new ProviderResolver(
            $this->createMock(SysConfigRepository::class),
            $this->createMock(ClientProviderRoutingRepository::class),
            $this->createMock(LoggerInterface::class),
        );

        return new ProviderContextFactory($resolver);
    }

    private function credentialsResolver(ProviderRegistry $registry, ?string $tokenValue): ProviderCredentialsResolver
    {
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $sysConfigRepo->method('findCachedValue')->willReturnMap([
            ['provider.dtone.test.token', true, $tokenValue],
        ]);

        return new ProviderCredentialsResolver($sysConfigRepo, $registry);
    }

    public function testPingIsInconclusiveWhenNotFullyConfigured(): void
    {
        $adapter = new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [new ProviderConfigField('token', 'Token', required: true, secret: false)];
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, null), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertTrue($result->inconclusive);
        $this->assertFalse($result->available);
    }

    public function testPingDelegatesToHealthCheckWhenAvailable(): void
    {
        $adapter = new class implements ProviderHealthCheckInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function checkHealth(ProviderContext $context): ProviderPingResult
            {
                return ProviderPingResult::available(42);
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertTrue($result->available);
        $this->assertSame(42, $result->latencyMs);
    }

    public function testPingFallsBackToBalanceWhenNoHealthCheck(): void
    {
        $adapter = new class implements ProviderBalanceInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
            {
                return new ProviderBalanceResult(['USD' => 10.0], new \DateTimeImmutable('2026-08-01T00:00:00+00:00'));
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertTrue($result->available);
        $this->assertSame(['USD' => 10.0], $result->details['amounts']);
    }

    public function testPingIsInconclusiveWhenAdapterHasNoProbe(): void
    {
        $adapter = new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertTrue($result->inconclusive);
    }

    public function testPingReturnsUnavailableWhenHealthCheckThrows(): void
    {
        $adapter = new class implements ProviderHealthCheckInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function checkHealth(ProviderContext $context): ProviderPingResult
            {
                throw new MyCurrentException('DTONE_TIMEOUT', 'timeout', 503);
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertFalse($result->available);
        $this->assertFalse($result->inconclusive);
        $this->assertSame('timeout', $result->error);
    }

    public function testPreferBalancePrefersBalanceOverHealthCheckWhenAdapterSupportsBoth(): void
    {
        $adapter = new class implements ProviderHealthCheckInterface, ProviderBalanceInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function checkHealth(ProviderContext $context): ProviderPingResult
            {
                // El botón manual "probar conexión" (preferBalance: true) nunca
                // debe llegar aquí cuando el adaptador también soporta balance
                // — si este método se invoca, el test debe fallar de forma
                // visible en vez de devolver un resultado que enmascare el bug.
                throw new \LogicException('checkHealth() no debía llamarse con preferBalance: true');
            }

            public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
            {
                return new ProviderBalanceResult(['USD' => 374.0], new \DateTimeImmutable('2026-08-20T00:00:00+00:00'));
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST', preferBalance: true);

        $this->assertTrue($result->available);
        $this->assertSame(['USD' => 374.0], $result->details['amounts']);
    }

    public function testPreferBalanceFallsBackToHealthCheckWhenAdapterHasNoBalance(): void
    {
        $adapter = new class implements ProviderHealthCheckInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            public function getCapabilities(): array
            {
                return [];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function checkHealth(ProviderContext $context): ProviderPingResult
            {
                return ProviderPingResult::available(7);
            }
        };
        $registry = new ProviderRegistry([$adapter]);

        $service = new ProviderPingService($registry, $this->credentialsResolver($registry, 'x'), $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::DTONE, 'TEST', preferBalance: true);

        $this->assertTrue($result->available);
        $this->assertSame(7, $result->latencyMs);
    }

    public function testPingReturnsUnavailableWhenProviderNotRegistered(): void
    {
        $registry = new ProviderRegistry([]);
        $credentialsResolver = new ProviderCredentialsResolver($this->createMock(SysConfigRepository::class), $registry);

        $service = new ProviderPingService($registry, $credentialsResolver, $this->contextFactory());

        $result = $service->ping(CommunicationProviderEnum::ETECSA, 'PROD');

        $this->assertFalse($result->available);
        $this->assertStringContainsString('no está registrado', $result->error);
    }
}
