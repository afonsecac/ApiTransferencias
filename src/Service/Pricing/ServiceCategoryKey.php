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
     *
     * `$area` es opcional (ADR-001, mercado): SIN área devuelve exactamente la
     * clave de siempre, así que ninguna fila existente cambia ni necesita
     * backfill; CON área añade un tercer segmento, "{name}|{subserviceName}|{area}".
     */
    public static function of(?string $name, ?string $subserviceName, ?string $area = null): string
    {
        $key = sprintf('%s|%s', trim($name ?? ''), trim($subserviceName ?? ''));
        $area = trim($area ?? '');

        return $area === '' ? $key : $key . '|' . $area;
    }

    /**
     * @param array{name?: string, subservice?: array{name?: string}, area?: array{name?: string}} $service
     */
    public static function fromService(array $service): string
    {
        return self::of(
            $service['name'] ?? null,
            $service['subservice']['name'] ?? null,
            $service['area']['name'] ?? null,
        );
    }

    /**
     * ¿`$key` es de la categoría `$category` o de una versión de ella con área? Igual a la categoría o
     * con el sufijo "|{area}": así un filtro por servicio + subservicio sigue encontrando los paquetes
     * que ya llevan área, y "Mobile|AIRTIME" no confunde "Mobile|AIRTIMEX". El inverso no vale: una
     * clave sin área no pertenece a una categoría que sí la pide.
     */
    public static function isInCategory(string $key, string $category): bool
    {
        return $key === $category || str_starts_with($key, $category . '|');
    }

    public static function matches(?string $nameA, ?string $subserviceA, ?string $nameB, ?string $subserviceB): bool
    {
        return self::of($nameA, $subserviceA) === self::of($nameB, $subserviceB);
    }
}
