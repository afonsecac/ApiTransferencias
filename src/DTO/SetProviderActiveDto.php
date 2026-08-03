<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Activa o desactiva manualmente un proveedor para un entorno (TEST/PROD),
 * independientemente de si tiene sus claves obligatorias configuradas —
 * ver ProviderCredentialsResolver::isActive(). Sirve para apagar un
 * proveedor puntualmente (p.ej. incidencia con CSQ) sin borrar sus
 * credenciales.
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
