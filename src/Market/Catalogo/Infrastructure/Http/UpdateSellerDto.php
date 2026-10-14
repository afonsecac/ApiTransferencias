<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/sellers/{id}: nombre y centro de costo. El identificador y la unidad no cambian.
 */
class UpdateSellerDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    #[Assert\Positive]
    protected ?int $costCenterId;

    public function __construct(?string $name = null, ?int $costCenterId = null)
    {
        $this->name = $name;
        $this->costCenterId = $costCenterId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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
