<?php

namespace App\Provider\Contract;

/**
 * Proveedores con un endpoint de ping dedicado (más barato y más fiel que
 * usar getPlatformBalance() como prueba de vida). Implementada hoy por ETECSA
 * y CSQ; DTOne no la implementa y cae al fallback de
 * App\Service\Provider\ProviderPingService.
 */
interface ProviderHealthCheckInterface extends CommunicationProviderInterface
{
    public function checkHealth(ProviderContext $context): ProviderPingResult;
}
