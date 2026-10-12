<?php

namespace App\Service;

use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Punto único para decidir según el subtipo de venta (ADR-001, COMR-62). Las ventas se guardan en una sola
 * tabla con subtipos (recarga, paquete…); hasta ahora varios sitios hacían `if recarga … elseif paquete` sin
 * rama final, o `recarga ? A : B`, y un subtipo nuevo se perdía en silencio o se trataba como «paquete».
 * Aquí, un subtipo que no se conoce lanza un error de dominio: al añadir el subtipo de mercado, cualquier
 * sitio que se olvide de contemplarlo falla en las pruebas en lugar de perder ventas.
 */
final class SaleSubtype
{
    public const SALE_TYPE_RECHARGE = 'recharge';
    public const SALE_TYPE_SALE = 'sale';

    private function __construct()
    {
        // Solo métodos estáticos — no instanciar.
    }

    /**
     * Tipo de venta tal como lo usan el routing y la elección de proveedor: 'recharge' o 'sale'.
     *
     * @throws MyCurrentException ante cualquier subtipo que no sea recarga ni paquete
     */
    public static function saleTypeOf(object $sale): string
    {
        return match (true) {
            $sale instanceof CommunicationSaleRecharge => self::SALE_TYPE_RECHARGE,
            $sale instanceof CommunicationSalePackage => self::SALE_TYPE_SALE,
            default => throw self::unsupported($sale),
        };
    }

    /**
     * Error para los sitios que bifurcan por su cuenta (porque despachan un mensaje distinto por subtipo, etc.).
     */
    public static function unsupported(object $sale): MyCurrentException
    {
        return new MyCurrentException(
            'UNSUPPORTED_SALE_SUBTYPE',
            sprintf('Subtipo de venta no soportado: %s', $sale::class),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
