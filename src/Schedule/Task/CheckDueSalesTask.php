<?php

namespace App\Schedule\Task;

use App\Service\CommunicationSaleService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Consulta de estado de las ventas pendientes (COMR-260 paso 2). Cada 15 segundos reclama las ventas cuyo
 * `next_check_at` ya venció (o es nulo) y encola una consulta por cada una; la consulta reprograma `next_check_at` con
 * una espera creciente (5 s, 15 s, 1 min, 5 min, 15 min, 1 h). Sustituye al barrido horario de todas las pendientes
 * (CheckStatusTask) y a las cadenas de reintentos de Messenger.
 *
 * Solo maneja identificadores: no carga entidades ni consulta al proveedor aquí, así que no bloquea el
 * worker-scheduler ni su memoria crece con el histórico.
 */
#[AsPeriodicTask(frequency: '15 seconds')]
class CheckDueSalesTask
{
    /** Tope de ventas por proveedor y pasada: uno con muchas pendientes no deja sin turno a los demás. */
    public const PER_PROVIDER_LIMIT = 20;

    /**
     * Cuánto se «arrienda» una venta reclamada. Tiene que durar más que una consulta lenta (el cliente de ETECSA
     * corta a los 5 s) para que no se solape con la pasada siguiente; si el worker muere, caduca y vuelve a tocar.
     */
    public const LEASE_SECONDS = 90;

    public function __construct(
        private readonly CommunicationSaleService $communicationSaleService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        try {
            $this->communicationSaleService->dispatchDueStatusChecks(self::PER_PROVIDER_LIMIT, self::LEASE_SECONDS);
        } catch (\Throwable $e) {
            $this->logger->warning('CheckDueSalesTask: could not run, will retry on next tick. ' . $e->getMessage());
        }
    }
}
