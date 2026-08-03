<?php

namespace App\Service\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;

/**
 * Prueba en vivo, bajo demanda, si las credenciales configuradas para un
 * proveedor/entorno funcionan — consultando el saldo de plataforma, la
 * operación de solo-lectura más barata disponible en todos los proveedores.
 * No hay circuit breaker ni estado persistido: es una señal honesta del
 * instante en que se pulsa el botón, no un histórico de fiabilidad.
 */
class ProviderConnectionTestService
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly ProviderContextFactory $contextFactory,
    ) {
    }

    /**
     * @return array{success: bool, amounts?: array<string, float>, fetchedAt?: string, message?: string}
     */
    public function test(CommunicationProviderEnum $provider, string $environmentType): array
    {
        try {
            $adapter = $this->providerRegistry->getFor($provider, ProviderBalanceInterface::class);
            $result = $adapter->getPlatformBalance($this->contextFactory->forEnvironmentType($provider, $environmentType));

            return [
                'success' => true,
                'amounts' => $result->amounts,
                'fetchedAt' => $result->fetchedAt->format(DATE_ATOM),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
