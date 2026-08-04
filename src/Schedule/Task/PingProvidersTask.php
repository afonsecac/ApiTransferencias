<?php

namespace App\Schedule\Task;

use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Service\CommunicationsDispatchService;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderPingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Ping cada 15 minutos a cada proveedor registrado, por entorno (TEST/PROD).
 * Solo pinguea los que tienen credenciales completas — un proveedor sin
 * configurar no es "caído", es "no aplica" (ProviderPingService ya lo trata
 * como inconclusive, pero evitamos la llamada HTTP innecesaria).
 *
 * Un proveedor que lanza no debe impedir pinguear al resto: cada par
 * (proveedor, entorno) se captura por separado. Sin riesgo de solape: este
 * transport (scheduler_default) lo consume un único worker-scheduler de
 * forma secuencial (ver docker-compose.vps.prod.yaml) — una ejecución lenta
 * solo retrasa el siguiente disparo, nunca corre en paralelo consigo misma.
 */
#[AsCronTask('*/15 * * * *', 'America/Havana')]
class PingProvidersTask
{
    private const ENVIRONMENT_TYPES = ['TEST', 'PROD'];

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        private readonly ProviderPingService $pingService,
        private readonly ProviderAvailabilityService $availabilityService,
        private readonly CommunicationsDispatchService $dispatchService,
        #[Autowire('@monolog.logger.provider')] private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        foreach ($this->registry->registered() as $provider) {
            foreach (self::ENVIRONMENT_TYPES as $environmentType) {
                try {
                    if (!$this->credentialsResolver->isFullyConfigured($provider, $environmentType)) {
                        continue;
                    }

                    $result = $this->pingService->ping($provider, $environmentType);
                    $justRecovered = $this->availabilityService->recordPing($provider, $environmentType, $result);

                    if ($justRecovered) {
                        $this->dispatchService->redispatchPendingFor($provider->value, $environmentType);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error('Ping de proveedor falló inesperadamente', [
                        'provider' => $provider->value,
                        'environmentType' => $environmentType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
