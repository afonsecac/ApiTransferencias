<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/measure-settings. Las unidades las valida el dominio (tipo de cada una).
 */
class ChangeMeasureSettingsDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 8)]
    protected ?string $weightBase;

    #[Assert\NotBlank]
    #[Assert\Length(max: 8)]
    protected ?string $volumeBase;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $volumetricDivisor;

    public function __construct(?string $weightBase = null, ?string $volumeBase = null, ?int $volumetricDivisor = null)
    {
        $this->weightBase = $weightBase;
        $this->volumeBase = $volumeBase;
        $this->volumetricDivisor = $volumetricDivisor;
    }

    public function getWeightBase(): ?string
    {
        return $this->weightBase;
    }

    public function setWeightBase(?string $weightBase): void
    {
        $this->weightBase = $weightBase;
    }

    public function getVolumeBase(): ?string
    {
        return $this->volumeBase;
    }

    public function setVolumeBase(?string $volumeBase): void
    {
        $this->volumeBase = $volumeBase;
    }

    public function getVolumetricDivisor(): ?int
    {
        return $this->volumetricDivisor;
    }

    public function setVolumetricDivisor(?int $volumetricDivisor): void
    {
        $this->volumetricDivisor = $volumetricDivisor;
    }
}
