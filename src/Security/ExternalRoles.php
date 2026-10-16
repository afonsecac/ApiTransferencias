<?php

namespace App\Security;

/**
 * Roles de personas EXTERNAS a la plataforma (COMR-288): no entran en la jerarquía del dashboard ni en la administración del
 * mercado y solo operan por su propio prefijo, con 2FA obligatorio y aislados por almacén.
 */
final class ExternalRoles
{
    /** Persona de un socio de almacén. */
    public const WAREHOUSE_PARTNER = 'ROLE_MARKET_WAREHOUSE_USER';

    private function __construct()
    {
    }
}
