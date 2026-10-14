<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/brands/{id}: solo el nombre. El identificador y el vendedor no cambian.
 */
class UpdateBrandDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    protected ?string $name;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
