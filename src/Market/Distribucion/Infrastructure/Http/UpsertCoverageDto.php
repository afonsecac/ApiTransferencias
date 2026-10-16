<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/warehouses/{id}/coverage/{municipalityId}: las tarifas van como texto decimal (hasta 4
 * decimales) para no perder exactitud. Una tarifa 0 o un máximo vacío exigen su casilla de confirmación.
 */
class UpsertCoverageDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $ratePerKg;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $ratePerL;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $minPrice;

    #[Assert\Length(max: 20)]
    protected ?string $maxPrice;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 999)]
    protected ?int $priority;

    protected ?bool $confirmZeroRate;

    protected ?bool $confirmNoMax;

    public function __construct(?string $ratePerKg = null, ?string $ratePerL = null, ?string $minPrice = null, ?string $maxPrice = null, ?int $priority = null, ?bool $confirmZeroRate = null, ?bool $confirmNoMax = null)
    {
        $this->ratePerKg = $ratePerKg;
        $this->ratePerL = $ratePerL;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->priority = $priority;
        $this->confirmZeroRate = $confirmZeroRate;
        $this->confirmNoMax = $confirmNoMax;
    }

    public function getRatePerKg(): ?string
    {
        return $this->ratePerKg;
    }

    public function setRatePerKg(?string $ratePerKg): void
    {
        $this->ratePerKg = $ratePerKg;
    }

    public function getRatePerL(): ?string
    {
        return $this->ratePerL;
    }

    public function setRatePerL(?string $ratePerL): void
    {
        $this->ratePerL = $ratePerL;
    }

    public function getMinPrice(): ?string
    {
        return $this->minPrice;
    }

    public function setMinPrice(?string $minPrice): void
    {
        $this->minPrice = $minPrice;
    }

    public function getMaxPrice(): ?string
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?string $maxPrice): void
    {
        $this->maxPrice = $maxPrice;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): void
    {
        $this->priority = $priority;
    }

    public function getConfirmZeroRate(): ?bool
    {
        return $this->confirmZeroRate;
    }

    public function setConfirmZeroRate(?bool $confirmZeroRate): void
    {
        $this->confirmZeroRate = $confirmZeroRate;
    }

    public function getConfirmNoMax(): ?bool
    {
        return $this->confirmNoMax;
    }

    public function setConfirmNoMax(?bool $confirmNoMax): void
    {
        $this->confirmNoMax = $confirmNoMax;
    }
}
