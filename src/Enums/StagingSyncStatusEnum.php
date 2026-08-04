<?php

namespace App\Enums;

/**
 * Estado de una corrida de scripts/sync-prod-to-staging.sh (cron mensual o
 * disparo bajo demanda desde el dashboard). Ver App\Service\StagingSyncService.
 */
enum StagingSyncStatusEnum: string
{
    case RUNNING = 'RUNNING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
}
