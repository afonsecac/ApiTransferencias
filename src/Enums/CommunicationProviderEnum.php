<?php

namespace App\Enums;

/**
 * Proveedores soportados por el sistema: los externos de recargas/paquetes y el propio del mercado (OWN).
 * Ver src/Provider/Contract/CommunicationProviderInterface.php y
 * src/Provider/ProviderRegistry.php para el descubrimiento en runtime.
 */
enum CommunicationProviderEnum: string
{
    case ETECSA = 'ETECSA';
    case DTONE = 'DTONE';
    case CSQ = 'CSQ';
    /** Proveedor propio del mercado (ADR-001): opera solo en el dominio de mercado, nunca en comunicaciones. */
    case OWN = 'OWN';

    /**
     * Todos los códigos, de cualquier dominio.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    /**
     * Códigos que se pueden elegir en el enrutado de comunicaciones. Es lo que validan los DTOs del routing:
     * OWN existe como código pero no se puede asignar a una regla de recargas o paquetes.
     *
     * @return list<string>
     */
    public static function communicationsValues(): array
    {
        return array_values(array_map(
            static fn (self $c) => $c->value,
            array_filter(self::cases(), static fn (self $c) => in_array(ProviderDomainEnum::Communications, $c->domains(), true)),
        ));
    }

    /**
     * Dominios en los que opera este código. Cada adaptador declara los suyos con
     * ProviderDomainAwareInterface y un test comprueba que ambos coinciden; el enum permite decidirlo sin
     * necesitar el registro (p. ej. en la validación de los DTOs).
     *
     * @return list<ProviderDomainEnum>
     */
    public function domains(): array
    {
        return match ($this) {
            self::ETECSA, self::DTONE, self::CSQ => [ProviderDomainEnum::Communications],
            self::OWN => [ProviderDomainEnum::Market],
        };
    }
}
