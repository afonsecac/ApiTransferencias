<?php

namespace App\Service\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\Contract\ProviderContext;
use App\Provider\Contract\ProviderHealthCheckInterface;
use App\Provider\Contract\ProviderPingResult;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;

/**
 * Sondeo único de disponibilidad de un proveedor, usado tanto por el ping
 * periódico (App\Schedule\Task\PingProvidersTask) como por el botón de
 * "probar conexión" del dashboard (App\Service\Provider\ProviderConnectionTestService).
 *
 * Orden de preferencia por defecto (ping periódico, cada 15 min — no debe
 * generar consultas de saldo constantes a cada proveedor): si el adaptador
 * tiene un ping dedicado (ProviderHealthCheckInterface) se usa ese; si no,
 * getPlatformBalance() como prueba de vida barata; si tampoco, inconclusive
 * (no hay forma de sondear).
 *
 * `$preferBalance` invierte esa prioridad — lo usa exclusivamente el botón
 * manual "probar conexión" del dashboard (confirmado con el usuario:
 * ahí SIEMPRE debe consultarse el balance real, no solo un ping barato,
 * aunque el proveedor tenga su propio health-check; el ping automático de
 * 15 min sigue sin tocarse).
 */
class ProviderPingService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        private readonly ProviderContextFactory $contextFactory,
    ) {
    }

    public function ping(CommunicationProviderEnum $provider, string $environmentType, bool $preferBalance = false): ProviderPingResult
    {
        try {
            if (!$this->credentialsResolver->isFullyConfigured($provider, $environmentType)) {
                return ProviderPingResult::inconclusive('Proveedor sin credenciales completas para este entorno');
            }

            $adapter = $this->registry->get($provider);
            $context = $this->contextFactory->forEnvironmentType($provider, $environmentType);

            if ($preferBalance && $adapter instanceof ProviderBalanceInterface) {
                return $this->pingViaBalance($adapter, $context);
            }

            if ($adapter instanceof ProviderHealthCheckInterface) {
                return $adapter->checkHealth($context);
            }

            if ($adapter instanceof ProviderBalanceInterface) {
                return $this->pingViaBalance($adapter, $context);
            }
        } catch (\Throwable $e) {
            return ProviderPingResult::unavailable($e->getMessage());
        }

        return ProviderPingResult::inconclusive('Proveedor sin ping ni prueba de saldo disponibles');
    }

    private function pingViaBalance(ProviderBalanceInterface $adapter, ProviderContext $context): ProviderPingResult
    {
        $start = microtime(true);
        $balance = $adapter->getPlatformBalance($context);

        return ProviderPingResult::available(
            (int) round((microtime(true) - $start) * 1000),
            ['amounts' => $balance->amounts, 'fetchedAt' => $balance->fetchedAt->format(DATE_ATOM)],
        );
    }
}
