<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/org-units/{id}/cost-centers/{code}: nombre y estado. El código no cambia
 * y un centro no se borra, se desactiva.
 */
class UpdateCostCenterDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $name;

    #[Assert\NotNull]
    protected ?bool $active;

    public function __construct(?string $name = null, ?bool $active = null)
    {
        $this->name = $name;
        $this->active = $active;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }
}
