<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;

/**
 * El conjunto de claves válido lo define cada proveedor en runtime
 * (CommunicationProviderInterface::getConfigSchema()) — no es un shape
 * estático conocido en tiempo de compilación, así que en vez de propiedades
 * tipadas con #[Assert\] individuales se usa un mapa dinámico. La
 * validación de qué claves son válidas para el proveedor de turno (y su
 * cifrado según el esquema) vive en ProviderCredentialsAdminService::upsert(),
 * no aquí.
 *
 * Una clave ausente del mapa significa "no tocar" ese campo — igual que
 * UpdateSysConfigDto — así se puede actualizar solo el api_key sin reenviar
 * el base_url, por ejemplo.
 */
class UpdateProviderCredentialsDto implements IInput
{
    /**
     * @var array<string,string>|null
     */
    #[OAProperty(description: 'Mapa clave=>valor de los campos a actualizar (según el esquema del proveedor). Una clave ausente no se toca')]
    protected ?array $values;

    /**
     * @param array<string,string>|null $values
     */
    public function __construct(?array $values = null)
    {
        $this->values = $values;
    }

    /**
     * @return array<string,string>|null
     */
    public function getValues(): ?array
    {
        return $this->values;
    }

    /**
     * @param array<string,string>|null $values
     */
    public function setValues(?array $values): void
    {
        $this->values = $values;
    }
}
