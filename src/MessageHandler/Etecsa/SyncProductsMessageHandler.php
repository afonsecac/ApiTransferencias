<?php

namespace App\MessageHandler\Etecsa;

use App\Entity\Environment;
use App\Message\Etecsa\SyncProductsMessage;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaCatalogSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncProductsMessageHandler
{
    public function __construct(
        private readonly EtecsaCatalogSyncService $syncService,
        private readonly EnvironmentRepository $environmentRepository,
        #[Autowire('@monolog.logger.etecsa')] private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProductsMessage $message): void
    {
        $env = $this->environmentRepository->find($message->environmentId);

        if (!$env instanceof Environment) {
            $this->logger->warning('SyncProducts: environment not found', ['id' => $message->environmentId]);
            return;
        }

        $result = $this->syncService->syncProducts($env);

        $this->logger->info('SyncProducts completed', [
            'environment' => $env->getType(),
            'products' => (string) $result,
        ]);
    }
}
