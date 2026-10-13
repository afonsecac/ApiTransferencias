<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/packages/sales/uncertain/{id}/resolve (COMR-258): cómo se resuelve a mano una
 * venta incierta y por qué. El motivo es obligatorio porque queda en el histórico junto al usuario.
 */
class ResolveUncertainSaleDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['completed', 'rejected'])]
    protected ?string $resolution;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 500)]
    protected ?string $reason;

    public function __construct(
        ?string $resolution = null,
        ?string $reason = null,
    ) {
        $this->resolution = $resolution;
        $this->reason = $reason;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): void
    {
        $this->resolution = $resolution;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }
}
