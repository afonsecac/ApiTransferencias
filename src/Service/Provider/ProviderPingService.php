<?php

namespace App\Service\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderHealthCheckInterface;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;

/**
 * Sondeo único de disponibilidad de un proveedor, usado tanto por el ping
 * periódico (App\Schedule\Task\PingProvidersTask) como por el botón de
 * "probar conexión" del dashboard (App\Service\Provider\ProviderConnectionTestService).
 * Una sola ruta de sondeo, un solo comportamiento.
 *
 * Orden de preferencia: si el adaptador tiene un ping dedicado
 * (ProviderHealthCheckInterface) se usa ese; si no, getPlatformBalance() como
 * prueba de vida barata; si tampoco, inconclusive (no hay forma de sondear).
 */
class ProviderPingService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        private readonly ProviderContextFactory $contextFactory,
    ) {
    }

    public function ping(CommunicationProviderEnum $provider, string $environmentType): ProviderPingResult
    {
        try {
            if (!$this->credentialsResolver->isFullyConfigured($provider, $environmentType)) {
                return ProviderPingResult::inconclusive('Proveedor sin credenciales completas para este entorno');
            }

            $adapter = $this->registry->get($provider);
            $context = $this->contextFactory->forEnvironmentType($provider, $environmentType);

            if ($adapter instanceof ProviderHealthCheckInterface) {
                return $adapter->checkHealth($context);
            }

            if ($adapter instanceof ProviderBalanceInterface) {
                $start = microtime(true);
                $balance = $adapter->getPlatformBalance($context);

                return ProviderPingResult::available(
                    (int) round((microtime(true) - $start) * 1000),
                    ['amounts' => $balance->amounts, 'fetchedAt' => $balance->fetchedAt->format(DATE_ATOM)],
                );
            }
        } catch (\Throwable $e) {
            return ProviderPingResult::unavailable($e->getMessage());
        }

        return ProviderPingResult::inconclusive('Proveedor sin ping ni prueba de saldo disponibles');
    }
}
