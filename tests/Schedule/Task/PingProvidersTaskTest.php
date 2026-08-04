<?php

namespace App\Tests\Schedule\Task;

use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\CommunicationProviderInterface;
use App\Provider\Contract\ProviderConfigField;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\SysConfigRepository;
use App\Schedule\Task\PingProvidersTask;
use App\Service\CommunicationsDispatchService;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderPingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \App\Schedule\Task\PingProvidersTask
 */
class PingProvidersTaskTest extends TestCase
{
    // ProviderRegistry y ProviderCredentialsResolver son `final`: se
    // construyen reales, con adaptadores fake de esquema vacío — así
    // isFullyConfigured() siempre da true sin necesitar tocar sysConfigRepo.
    private function fakeAdapter(CommunicationProviderEnum $code): CommunicationProviderInterface
    {
        return new class($code) implements CommunicationProviderInterface {
            public function __construct(private readonly CommunicationProviderEnum $code)
            {
            }

            public function getCode(): CommunicationProviderEnum
            {
                return $this->code;
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
    }

    public function testOneProviderThrowingDoesNotStopTheOthersFromBeingPinged(): void
    {
        $registry = new ProviderRegistry([
            $this->fakeAdapter(CommunicationProviderEnum::ETECSA),
            $this->fakeAdapter(CommunicationProviderEnum::CSQ),
        ]);
        $credentialsResolver = new ProviderCredentialsResolver($this->createMock(SysConfigRepository::class), $registry);

        $pingService = $this->createMock(ProviderPingService::class);
        $pingService->expects($this->exactly(4)) // 2 proveedores x 2 entornos
            ->method('ping')
            ->willReturnCallback(function (CommunicationProviderEnum $provider) {
                if ($provider === CommunicationProviderEnum::ETECSA) {
                    throw new \RuntimeException('fallo inesperado de red');
                }

                return ProviderPingResult::available(10);
            });

        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $availabilityService->method('recordPing')->willReturn(false);
        // Solo CSQ llega a recordPing() en cada entorno: ETECSA lanza antes.
        $availabilityService->expects($this->exactly(2))->method('recordPing');

        $dispatchService = $this->createMock(CommunicationsDispatchService::class);
        $dispatchService->expects($this->never())->method('redispatchPendingFor');

        $task = new PingProvidersTask(
            $registry,
            $credentialsResolver,
            $pingService,
            $availabilityService,
            $dispatchService,
            new NullLogger(),
        );

        // No debe propagar la excepción de ETECSA.
        $task();
    }

    public function testRedispatchesPendingOnlyWhenRecordPingReportsRecovery(): void
    {
        $registry = new ProviderRegistry([$this->fakeAdapter(CommunicationProviderEnum::CSQ)]);
        $credentialsResolver = new ProviderCredentialsResolver($this->createMock(SysConfigRepository::class), $registry);

        $pingService = $this->createMock(ProviderPingService::class);
        $pingService->method('ping')->willReturn(ProviderPingResult::available(5));

        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $availabilityService->method('recordPing')->willReturnOnConsecutiveCalls(true, false);

        $dispatchService = $this->createMock(CommunicationsDispatchService::class);
        $dispatchService->expects($this->once())
            ->method('redispatchPendingFor')
            ->with('CSQ', $this->isType('string'));

        $task = new PingProvidersTask($registry, $credentialsResolver, $pingService, $availabilityService, $dispatchService, new NullLogger());

        $task();
    }

    public function testSkipsProvidersWithoutFullCredentials(): void
    {
        $adapter = new class implements CommunicationProviderInterface {
            public function getCode(): CommunicationProviderEnum
            {
                return CommunicationProviderEnum::CSQ;
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
        // sysConfigRepo mockeado sin ningún valor configurado: isFullyConfigured() da false para ambos entornos.
        $credentialsResolver = new ProviderCredentialsResolver($this->createMock(SysConfigRepository::class), $registry);

        $pingService = $this->createMock(ProviderPingService::class);
        $pingService->expects($this->never())->method('ping');

        $availabilityService = $this->createMock(ProviderAvailabilityService::class);
        $availabilityService->expects($this->never())->method('recordPing');

        $dispatchService = $this->createMock(CommunicationsDispatchService::class);

        $task = new PingProvidersTask($registry, $credentialsResolver, $pingService, $availabilityService, $dispatchService, new NullLogger());

        $task();
    }
}
