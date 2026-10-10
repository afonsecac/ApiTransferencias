<?php

namespace App\Provider;

/**
 * Resultado de ProviderDispatchResolver::selectOrDefer() (y del equivalente
 * de promociones) cuando ningún candidato está despachable AHORA pero al
 * menos uno sí cubre el destino: la venta se acepta sin proveedor asignado y
 * se resuelve más tarde, cuando alguno se recupere. No es un error — un
 * destino que ningún proveedor cubre sigue lanzando 409.
 */
final readonly class DeferredDispatch
{
}
