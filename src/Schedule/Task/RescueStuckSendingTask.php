<?php

namespace App\Schedule\Task;

use App\Service\CommunicationSaleService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Rescata las ventas colgadas en SENDING (COMR-257): las marca inciertas y las consulta, sin reenviarlas.
 */
#[AsCronTask("*/5 * * * *", "America/Havana")]
class RescueStuckSendingTask
{
    /** Margen sobre lo que tarda un envío normal; antes de esto una venta en SENDING puede seguir en curso. */
    public const OLDER_THAN_MINUTES = 15;

    public function __construct(
        private readonly CommunicationSaleService $communicationSaleService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        try {
            $this->communicationSaleService->rescueStuckSendingSales(self::OLDER_THAN_MINUTES);
        } catch (\Throwable $e) {
            $this->logger->warning('RescueStuckSendingTask: could not run, will retry on next tick. ' . $e->getMessage());
        }
    }
}
