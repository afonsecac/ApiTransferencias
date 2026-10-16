<?php

declare(strict_types=1);

namespace App\Market\Geografia\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/geography/{provinces|municipalities}/{id}/active.
 */
class SetActiveDto implements IInput
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
