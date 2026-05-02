<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SyncProductsDto implements IInput
{
    #[Assert\NotBlank]
    protected ?string $environmentType;

    public function __construct(?string $environmentType = null)
    {
        $this->environmentType = $environmentType;
    }

    public function getEnvironmentType(): ?string { return $this->environmentType; }
    public function setEnvironmentType(?string $v): void { $this->environmentType = $v; }
}
