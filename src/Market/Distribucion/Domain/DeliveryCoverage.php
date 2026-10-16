<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Cobertura (COMR-72): una fila por (almacén, municipio) con las tarifas lineales de entrega, la prioridad y su estado. «Dejar de
 * cubrir» la desactiva, no la borra. La prioridad decide quién atiende cuando varios almacenes activos cubren el municipio (gana
 * el número más bajo); no cambia el precio, que es el del almacén que atiende. El modo de tarifa (`rate_mode`) hoy es solo `linear`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_delivery_coverage')]
#[ORM\UniqueConstraint(name: 'uniq_market_delivery_coverage', columns: ['warehouse_id', 'municipality_id'])]
#[ORM\Index(name: 'idx_market_delivery_coverage_municipality', columns: ['municipality_id'])]
class DeliveryCoverage
{
    public const MODE_LINEAR = 'linear';
    public const MAX_PRIORITY = 999;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'warehouse_id', type: 'integer')]
    private int $warehouseId;

    #[ORM\Column(name: 'municipality_id', type: 'integer')]
    private int $municipalityId;

    #[ORM\Column(name: 'rate_mode', type: 'string', length: 12)]
    private string $rateMode = self::MODE_LINEAR;

    #[ORM\Column(name: 'rate_per_kg', type: 'decimal', precision: 12, scale: 4)]
    private string $ratePerKg;

    #[ORM\Column(name: 'rate_per_l', type: 'decimal', precision: 12, scale: 4)]
    private string $ratePerL;

    #[ORM\Column(name: 'min_price', type: 'decimal', precision: 12, scale: 4)]
    private string $minPrice;

    #[ORM\Column(name: 'max_price', type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $maxPrice;

    #[ORM\Column(type: 'integer')]
    private int $priority;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(int $warehouseId, int $municipalityId, DeliveryRates $rates, int $priority)
    {
        if ($warehouseId <= 0 || $municipalityId <= 0) {
            throw new InvalidCoverage('Una cobertura necesita almacén y municipio.');
        }
        $this->warehouseId = $warehouseId;
        $this->municipalityId = $municipalityId;
        $this->applyRates($rates, $priority);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(int $warehouseId, int $municipalityId, DeliveryRates $rates, int $priority): self
    {
        return new self($warehouseId, $municipalityId, $rates, $priority);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function warehouseId(): int
    {
        return $this->warehouseId;
    }

    public function municipalityId(): int
    {
        return $this->municipalityId;
    }

    public function rateMode(): string
    {
        return $this->rateMode;
    }

    public function rates(): DeliveryRates
    {
        return new DeliveryRates(
            DeliveryAmount::fromString($this->ratePerKg),
            DeliveryAmount::fromString($this->ratePerL),
            DeliveryAmount::fromString($this->minPrice),
            $this->maxPrice === null ? null : DeliveryAmount::fromString($this->maxPrice),
        );
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function reprice(DeliveryRates $rates, int $priority): void
    {
        $this->applyRates($rates, $priority);
        $this->touch();
    }

    public function stop(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function resume(): void
    {
        $this->active = true;
        $this->touch();
    }

    private function applyRates(DeliveryRates $rates, int $priority): void
    {
        if ($priority < 1 || $priority > self::MAX_PRIORITY) {
            throw new InvalidCoverage(sprintf('La prioridad es un número entre 1 y %d (gana el más bajo).', self::MAX_PRIORITY));
        }
        $this->ratePerKg = $rates->perKg->value();
        $this->ratePerL = $rates->perL->value();
        $this->minPrice = $rates->min->value();
        $this->maxPrice = $rates->max?->value();
        $this->priority = $priority;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
