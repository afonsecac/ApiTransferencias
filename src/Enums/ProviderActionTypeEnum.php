<?php

namespace App\Enums;

/**
 * Origen del último cambio de disponibilidad de un proveedor
 * (App\Entity\ProviderAvailability). MANUAL lo escribe un admin desde el
 * dashboard (se audita el usuario); AUTO lo escribe el ping periódico
 * (App\Schedule\Task\PingProvidersTask). Ver App\Service\Provider\ProviderAvailabilityService
 * para la regla de precedencia entre ambos.
 */
enum ProviderActionTypeEnum: string
{
    case MANUAL = 'MANUAL';
    case AUTO = 'AUTO';
}
