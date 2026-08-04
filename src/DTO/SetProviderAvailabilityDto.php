<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Toggle MANUAL de disponibilidad, auditado (usuario + motivo opcional) —
 * ver App\Service\Provider\ProviderAvailabilityService::setManual(). Activar
 * (`active: true`) requiere que el proveedor tenga sus claves obligatorias
 * configuradas para ese entorno, o falla con 409 PROVIDER_NOT_CONFIGURED.
 */
class SetProviderAvailabilityDto implements IInput
{
    #[Assert\NotNull]
    protected ?bool $active;

    #[Assert\Length(max: 255)]
    protected ?string $reason;

    public function __construct(?bool $active = null, ?string $reason = null)
    {
        $this->active = $active;
        $this->reason = $reason;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
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
