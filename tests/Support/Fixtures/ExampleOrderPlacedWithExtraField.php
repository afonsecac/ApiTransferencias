<?php

declare(strict_types=1);

namespace App\Tests\Support\Fixtures;

/**
 * Variante de ExampleOrderPlaced con un campo más. Se usa para comprobar que un cambio de forma
 * hace fallar el test de contrato; comparte el nombre corto del contrato mediante CONTRACT_NAME.
 */
final readonly class ExampleOrderPlacedWithExtraField
{
    public const CONTRACT_NAME = 'ExampleOrderPlaced';

    /**
     * @param list<array{productId: int, quantity: int}> $lines
     */
    public function __construct(
        public string $eventId,
        public \DateTimeImmutable $occurredAt,
        public int $tenantId,
        public int $orderId,
        public array $lines,
        public string $currency,
    ) {
    }
}
