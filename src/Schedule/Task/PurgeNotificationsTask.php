<?php

namespace App\Schedule\Task;

use App\Service\NotificationCenterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Sin esta purga, notification_receipt crece sin límite y el anti-join del
 * conteo de no leídas se degrada (ver NotificationRepository::applyVisibility).
 * Cada 10 minutos es más que suficiente para una tabla de este volumen.
 */
#[AsCronTask('*/10 * * * *')]
class PurgeNotificationsTask
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $result = $this->notificationCenter->purge();
        if ($result['notifications'] > 0 || $result['tickets'] > 0) {
            $this->logger->info('Notifications purge', $result);
        }
    }
}
