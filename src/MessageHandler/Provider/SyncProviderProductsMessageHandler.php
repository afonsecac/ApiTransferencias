<?php

namespace App\MessageHandler\Provider;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Message\Provider\SyncProviderProductsMessage;
use App\Repository\EnvironmentRepository;
use App\Service\Provider\CommunicationCatalogSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncProviderProductsMessageHandler
{
    public function __construct(
        private readonly CommunicationCatalogSyncService $syncService,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProviderProductsMessage $message): void
    {
        $env = $this->environmentRepository->find($message->environmentId);
        if (!$env instanceof Environment) {
            $this->logger->warning('SyncProviderProducts: environment not found', ['id' => $message->environmentId]);

            return;
        }

        $provider = CommunicationProviderEnum::tryFrom($message->providerCode);
        if ($provider === null) {
            $this->logger->warning('SyncProviderProducts: unknown provider', ['provider' => $message->providerCode]);

            return;
        }

        $result = $this->syncService->syncProducts($provider, $env);

        $this->logger->info('SyncProviderProducts completed', [
            'provider' => $provider->value,
            'environment' => $env->getType(),
            'products' => (string) $result,
        ]);
    }
}
