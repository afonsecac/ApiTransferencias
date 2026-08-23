<?php

namespace App\Service\Pricing;

/**
 * Normaliza la clasificación (service.name, service.subservice.name) de un
 * CommunicationPackage/CommunicationContract para poder compararla — mismo
 * espíritu que DestinationKey para la tupla monto/moneda, pero SIN
 * case-folding: a diferencia de una moneda (alfabeto ISO fijo, 3 letras),
 * `service`/`subservice` es texto libre proveniente de proveedores
 * (Mobile/Utilities/Airtime/Internet/...) y esta clave también se calcula
 * en SQL (columnas `service_key` derivadas por migración/backfill) — si
 * normalizáramos mayúsculas, PHP `strtoupper()` (byte-wise, ASCII) y
 * Postgres `upper()` (locale-aware) podrían divergir para nombres con
 * acentos u otros caracteres no-ASCII. Comparar con trim() puro evita esa
 * clase de bug: mismo resultado en cualquier lenguaje, sin ambigüedad.
 *
 * El vocabulario de `service`/`subservice` es controlado (documentado como
 * enum en los DTOs/entidades, ver CommunicationPackage) — nunca debería
 * contener el separador `|`; un nombre real con `|` se rechaza en el DTO de
 * entrada correspondiente, no se escapa aquí.
 */
final class ServiceCategoryKey
{
    private function __construct()
    {
        // Solo métodos estáticos — no instanciar.
    }

    /**
     * Clave de comparación normalizada: "{name}|{subserviceName}", ambos
     * recortados de espacios, cadena vacía si están ausentes — nunca null,
     * así sirve directo como key de array PHP (el separador `|` garantiza
     * que la clave nunca sea puramente numérica y se coerciona a int).
     */
    public static function of(?string $name, ?string $subserviceName): string
    {
        return sprintf('%s|%s', trim($name ?? ''), trim($subserviceName ?? ''));
    }

    /**
     * @param array{name?: string, subservice?: array{name?: string}} $service
     */
    public static function fromService(array $service): string
    {
        return self::of(
            $service['name'] ?? null,
            $service['subservice']['name'] ?? null,
        );
    }

    public static function matches(?string $nameA, ?string $subserviceA, ?string $nameB, ?string $subserviceB): bool
    {
        return self::of($nameA, $subserviceA) === self::of($nameB, $subserviceB);
    }
}
