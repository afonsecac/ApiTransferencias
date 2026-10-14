<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/categories/{id}: los nombres y la posición. El identificador no cambia y el padre se
 * cambia con POST /{id}/move.
 */
class UpdateCategoryDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $nameEs;

    #[Assert\Length(max: 120)]
    protected ?string $nameEn;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    protected ?int $position;

    public function __construct(?string $nameEs = null, ?string $nameEn = null, ?int $position = null)
    {
        $this->nameEs = $nameEs;
        $this->nameEn = $nameEn;
        $this->position = $position;
    }

    public function getNameEs(): ?string
    {
        return $this->nameEs;
    }

    public function setNameEs(?string $nameEs): void
    {
        $this->nameEs = $nameEs;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): void
    {
        $this->nameEn = $nameEn;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }
}
