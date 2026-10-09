<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body opcional de POST /dashboard/api/sales/{id}/retry. Ambos campos son
 * nullable a nivel de DTO porque solo son obligatorios para el camino
 * ETECSA (ver DashboardSalesController::retry()) — para los demás
 * proveedores/tipos de venta el endpoint sigue aceptando un body vacío,
 * igual que antes.
 */
class RetrySaleDto implements IInput
{
    #[Assert\Length(min: 5, max: 500)]
    protected ?string $reason;

    #[Assert\Choice(choices: ['check', 'resend'])]
    protected ?string $mode;

    public function __construct(
        ?string $reason = null,
        ?string $mode = null,
    ) {
        $this->reason = $reason;
        $this->mode = $mode;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }
}
