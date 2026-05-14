<?php

namespace App\MessageHandler\Etecsa;

use App\Entity\Environment;
use App\Message\Etecsa\SyncCatalogsMessage;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaCatalogSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncCatalogsMessageHandler
{
    public function __construct(
        private readonly EtecsaCatalogSyncService $syncService,
        private readonly EnvironmentRepository $environmentRepository,
        #[Autowire('@monolog.logger.etecsa')] private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncCatalogsMessage $message): void
    {
        $env = $this->environmentRepository->find($message->environmentId);

        if (!$env instanceof Environment) {
            $this->logger->warning('SyncCatalogs: environment not found', ['id' => $message->environmentId]);
            return;
        }

        $nat = $this->syncService->syncNationalities($env);
        $prv = $this->syncService->syncProvinces($env);
        $off = $this->syncService->syncOffices($env);

        $this->logger->info('SyncCatalogs completed', [
            'environment' => $env->getType(),
            'nationalities' => (string) $nat,
            'provinces' => (string) $prv,
            'offices' => (string) $off,
        ]);
    }
}
