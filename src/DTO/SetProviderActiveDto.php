<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Activa o desactiva manualmente un proveedor para un entorno (TEST/PROD).
 * Sirve para apagar un proveedor puntualmente (p.ej. incidencia con CSQ) sin
 * borrar sus credenciales. Activar (`active: true`) requiere que el
 * proveedor tenga sus claves obligatorias configuradas para ese entorno —
 * ver App\Service\Provider\ProviderAvailabilityService::setManual().
 */
class SetProviderActiveDto implements IInput
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
