<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class DispatchConfigDto implements IInput
{
    #[OAProperty(description: 'true para habilitar el dispatch a comunicaciones, false para pausarlo')]
    #[Assert\NotNull]
    protected ?bool $dispatchEnabled;

    public function __construct(?bool $dispatchEnabled = null)
    {
        $this->dispatchEnabled = $dispatchEnabled;
    }

    public function getDispatchEnabled(): ?bool
    {
        return $this->dispatchEnabled;
    }

    public function setDispatchEnabled(?bool $v): void
    {
        $this->dispatchEnabled = $v;
    }
}
