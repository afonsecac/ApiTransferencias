<?php

namespace App\Service\Provider;

use App\Enums\CommunicationProviderEnum;

/**
 * Prueba en vivo, bajo demanda, si las credenciales configuradas para un
 * proveedor/entorno funcionan. Delega el sondeo en ProviderPingService (la
 * misma ruta que usa el ping periódico — un solo comportamiento) y adapta su
 * ProviderPingResult a la forma de retorno histórica de este servicio.
 * No hay circuit breaker ni estado persistido: es una señal honesta del
 * instante en que se pulsa el botón, no un histórico de fiabilidad.
 */
class ProviderConnectionTestService
{
    public function __construct(
        private readonly ProviderPingService $pingService,
    ) {
    }

    /**
     * @return array{success: bool, amounts?: array<string, float>, fetchedAt?: string, message?: string}
     */
    public function test(CommunicationProviderEnum $provider, string $environmentType): array
    {
        $result = $this->pingService->ping($provider, $environmentType);

        if ($result->available) {
            return [
                'success' => true,
                'amounts' => $result->details['amounts'] ?? [],
                'fetchedAt' => $result->details['fetchedAt'] ?? $result->checkedAt->format(DATE_ATOM),
            ];
        }

        return [
            'success' => false,
            'message' => $result->error ?? 'El proveedor no respondió',
        ];
    }
}
