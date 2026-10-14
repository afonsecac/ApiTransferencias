<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/sellers. El identificador (slug) y el centro de costo son opcionales: por
 * defecto se derivan del nombre y del centro principal de la unidad.
 */
class CreateSellerDto implements IInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $orgUnitId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\Length(max: 60)]
    protected ?string $slug;

    #[Assert\Positive]
    protected ?int $costCenterId;

    public function __construct(?int $orgUnitId = null, ?string $name = null, ?string $slug = null, ?int $costCenterId = null)
    {
        $this->orgUnitId = $orgUnitId;
        $this->name = $name;
        $this->slug = $slug;
        $this->costCenterId = $costCenterId;
    }

    public function getOrgUnitId(): ?int
    {
        return $this->orgUnitId;
    }

    public function setOrgUnitId(?int $orgUnitId): void
    {
        $this->orgUnitId = $orgUnitId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getCostCenterId(): ?int
    {
        return $this->costCenterId;
    }

    public function setCostCenterId(?int $costCenterId): void
    {
        $this->costCenterId = $costCenterId;
    }
}
