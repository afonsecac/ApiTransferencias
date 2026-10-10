<?php

namespace App\Schedule\Task;

use App\Service\CommunicationSaleService;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Red de seguridad del despacho diferido: el ping de proveedores ya
 * reintenta al recuperarse uno (PingProvidersTask), pero un proveedor
 * también vuelve a estar disponible por otras vías (interruptor manual,
 * switch global, credenciales) que no pasan por ese ping.
 */
#[AsCronTask('*/5 * * * *', 'America/Havana')]
class AwaitingProviderSalesTask
{
    public function __construct(
        private readonly CommunicationSaleService $saleService,
    ) {
    }

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(): void
    {
        $this->saleService->dispatchAwaitingProviderSales();
    }
}
