<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/warehouses/{id}/coverage/bulk: alta masiva por provincia. Con `apply` en falso solo cuenta lo
 * que haría («Crear 7 · Actualizar 8»); `overwrite` permite pisar los municipios ya cubiertos.
 */
class BulkCoverageDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $provinceId;

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

    protected ?bool $overwrite;

    protected ?bool $confirmZeroRate;

    protected ?bool $confirmNoMax;

    protected ?bool $apply;

    public function __construct(
        ?int $provinceId = null,
        ?string $ratePerKg = null,
        ?string $ratePerL = null,
        ?string $minPrice = null,
        ?string $maxPrice = null,
        ?int $priority = null,
        ?bool $overwrite = null,
        ?bool $confirmZeroRate = null,
        ?bool $confirmNoMax = null,
        ?bool $apply = null,
    ) {
        $this->provinceId = $provinceId;
        $this->ratePerKg = $ratePerKg;
        $this->ratePerL = $ratePerL;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->priority = $priority;
        $this->overwrite = $overwrite;
        $this->confirmZeroRate = $confirmZeroRate;
        $this->confirmNoMax = $confirmNoMax;
        $this->apply = $apply;
    }

    public function getProvinceId(): ?int
    {
        return $this->provinceId;
    }

    public function setProvinceId(?int $provinceId): void
    {
        $this->provinceId = $provinceId;
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

    public function getOverwrite(): ?bool
    {
        return $this->overwrite;
    }

    public function setOverwrite(?bool $overwrite): void
    {
        $this->overwrite = $overwrite;
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

    public function getApply(): ?bool
    {
        return $this->apply;
    }

    public function setApply(?bool $apply): void
    {
        $this->apply = $apply;
    }
}
