<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/delivery/quote. Cada línea es un producto por cantidad ({productId, quantity}) o, para la
 * vista previa, una carga suelta ({kg} o {liters}, como texto decimal de hasta 6 decimales).
 */
class QuoteDeliveryDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $municipalityId;

    /** @var list<array{productId?: int|null, quantity?: int|null, kg?: string|null, liters?: string|null}>|null */
    #[Assert\NotNull]
    #[Assert\Count(min: 1, max: 200)]
    protected ?array $lines;

    /**
     * @param list<array{productId?: int|null, quantity?: int|null, kg?: string|null, liters?: string|null}>|null $lines
     */
    public function __construct(?int $municipalityId = null, ?array $lines = null)
    {
        $this->municipalityId = $municipalityId;
        $this->lines = $lines;
    }

    public function getMunicipalityId(): ?int
    {
        return $this->municipalityId;
    }

    public function setMunicipalityId(?int $municipalityId): void
    {
        $this->municipalityId = $municipalityId;
    }

    /** @return list<array{productId?: int|null, quantity?: int|null, kg?: string|null, liters?: string|null}>|null */
    public function getLines(): ?array
    {
        return $this->lines;
    }

    /** @param list<array{productId?: int|null, quantity?: int|null, kg?: string|null, liters?: string|null}>|null $lines */
    public function setLines(?array $lines): void
    {
        $this->lines = $lines;
    }
}
