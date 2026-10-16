<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/warehouses/{id}/coverage/{municipalityId}/active.
 */
class SetCoverageActiveDto implements IInput
{
    #[Assert\NotNull]
    protected ?bool $active;

    public function __construct(?bool $active = null)
    {
        $this->active = $active;
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
