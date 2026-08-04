<?php

namespace App\Tests\Service\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderCapabilityEnum;
use App\Exception\MyCurrentException;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderBalanceResult;
use App\Provider\Contract\ProviderContext;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Repository\SysConfigRepository;
use App\Service\Provider\ProviderConnectionTestService;
use App\Service\Provider\ProviderPingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \App\Service\Provider\ProviderConnectionTestService
 */
class ProviderConnectionTestServiceTest extends TestCase
{
    private function contextFactory(): ProviderContextFactory
    {
        // ProviderResolver y ProviderContextFactory son `final`: se
        // instancian reales con dependencias mockeadas — forEnvironmentType()
        // no usa el resolver en absoluto, así que sus dobles nunca se llaman.
        $resolver = new ProviderResolver(
            $this->createMock(SysConfigRepository::class),
            $this->createMock(ClientProviderRoutingRepository::class),
            $this->createMock(LoggerInterface::class),
        );

        return new ProviderContextFactory($resolver);
    }

    private function service(ProviderRegistry $registry): ProviderConnectionTestService
    {
        // getConfigSchema() de los adaptadores de este test siempre es []:
        // ProviderCredentialsResolver::isFullyConfigured() no itera ningún
        // campo requerido y por tanto no llega a tocar SysConfigRepository.
        $credentialsResolver = new ProviderCredentialsResolver($this->createMock(SysConfigRepository::class), $registry);

        return new ProviderConnectionTestService(
            new ProviderPingService($registry, $credentialsResolver, $this->contextFactory()),
        );
    }

    public function testReturnsSuccessWithAmountsOnHappyPath(): void
    {
        $adapter = new class implements ProviderBalanceInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            /** @return list<ProviderCapabilityEnum> */
            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::BALANCE];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
            {
                return new ProviderBalanceResult(['USD' => 42.5], new \DateTimeImmutable('2026-08-01T00:00:00+00:00'));
            }
        };

        $service = $this->service(new ProviderRegistry([$adapter]));

        $result = $service->test(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertTrue($result['success']);
        $this->assertSame(['USD' => 42.5], $result['amounts']);
        $this->assertSame('2026-08-01T00:00:00+00:00', $result['fetchedAt']);
    }

    public function testReturnsFailureWhenAdapterThrows(): void
    {
        $adapter = new class implements ProviderBalanceInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::DTONE;
            }

            /** @return list<ProviderCapabilityEnum> */
            public function getCapabilities(): array
            {
                return [ProviderCapabilityEnum::BALANCE];
            }

            public function getConfigSchema(): array
            {
                return [];
            }

            public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult
            {
                throw new MyCurrentException('PROVIDER_CREDENTIALS_MISSING', 'faltan credenciales', 500);
            }
        };

        $service = $this->service(new ProviderRegistry([$adapter]));

        $result = $service->test(CommunicationProviderEnum::DTONE, 'TEST');

        $this->assertFalse($result['success']);
        $this->assertSame('faltan credenciales', $result['message']);
    }

    public function testReturnsFailureWhenProviderNotRegistered(): void
    {
        $service = $this->service(new ProviderRegistry([]));

        $result = $service->test(CommunicationProviderEnum::ETECSA, 'PROD');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no está registrado', $result['message']);
    }
}
