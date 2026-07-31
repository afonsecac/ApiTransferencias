<?php

namespace App\Provider\Contract;

use App\Enums\CommunicationProviderEnum;

/**
 * Contexto de invocación a un proveedor: reemplaza el parámetro `Environment`
 * que hoy reciben los métodos de EtecsaGatewayClient, para desacoplar el
 * transporte del ORM. Se construye con ProviderContextFactory.
 */
final readonly class ProviderContext
{
    public function __construct(
        public CommunicationProviderEnum $provider,
        public string $environmentType,
        public ?int $environmentId = null,
        public ?int $accountId = null,
        public ?int $clientId = null,
        public ?string $correlationId = null,
    ) {
    }
}
