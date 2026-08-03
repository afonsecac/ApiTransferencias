<?php

namespace App\Enums;

/**
 * Proveedores externos de recargas/paquetes soportados por el sistema.
 * Ver src/Provider/Contract/CommunicationProviderInterface.php y
 * src/Provider/ProviderRegistry.php para el descubrimiento en runtime.
 */
enum CommunicationProviderEnum: string
{
    case ETECSA = 'ETECSA';
    case DTONE = 'DTONE';
    case CSQ = 'CSQ';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
