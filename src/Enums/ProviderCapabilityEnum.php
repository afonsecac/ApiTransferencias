<?php

namespace App\Enums;

/**
 * Operaciones que un proveedor puede soportar. Un adaptador de proveedor
 * declara las suyas en getCapabilities(); ProviderRegistry::getFor() lanza
 * PROVIDER_CAPABILITY_UNSUPPORTED si se pide una capacidad no implementada
 * (p.ej. TOURIST_SIM/GEO_CATALOG en un proveedor que no sea ETECSA).
 */
enum ProviderCapabilityEnum: string
{
    case RECHARGE = 'RECHARGE';
    case PACKAGE_SALE = 'PACKAGE_SALE';
    case BALANCE = 'BALANCE';
    case CATALOG = 'CATALOG';
    case TOURIST_SIM = 'TOURIST_SIM';
    case GEO_CATALOG = 'GEO_CATALOG';
}
