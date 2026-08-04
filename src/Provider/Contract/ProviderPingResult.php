<?php

namespace App\Provider\Contract;

/**
 * Resultado de un ping de disponibilidad. Tres estados, no dos:
 * `inconclusive` es distinto de `unavailable` porque significa que nuestra
 * credencial o configuración está mal (401/403/409, sin configurar), no que
 * el proveedor esté caído — App\Service\Provider\ProviderAvailabilityService
 * nunca apaga un proveedor por un resultado inconclusive.
 */
final readonly class ProviderPingResult
{
    /**
     * @param array<string, mixed> $details
     */
    private function __construct(
        public bool $available,
        public bool $inconclusive,
        public ?int $latencyMs,
        public ?string $error,
        public \DateTimeImmutable $checkedAt,
        public array $details = [],
    ) {
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function available(?int $latencyMs = null, array $details = []): self
    {
        return new self(true, false, $latencyMs, null, new \DateTimeImmutable('now'), $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function unavailable(?string $error, ?int $latencyMs = null, array $details = []): self
    {
        return new self(false, false, $latencyMs, $error, new \DateTimeImmutable('now'), $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function inconclusive(?string $error, array $details = []): self
    {
        return new self(false, true, null, $error, new \DateTimeImmutable('now'), $details);
    }
}
