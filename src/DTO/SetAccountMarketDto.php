<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

class SetAccountMarketDto implements IInput
{
    #[OAProperty(description: 'true abre el mercado para esta cuenta; false lo cierra. Solo tiene efecto si además está encendido el interruptor global market.enabled')]
    #[Assert\NotNull]
    protected ?bool $enabled;

    public function __construct(?bool $enabled = null)
    {
        $this->enabled = $enabled;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
