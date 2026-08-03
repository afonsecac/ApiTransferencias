<?php

namespace App\Command\Provider;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Message\Provider\SyncProviderProductsMessage;
use App\Repository\EnvironmentRepository;
use App\Service\Provider\CommunicationCatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:provider:sync-products',
    description: 'Sincroniza el catálogo de productos de un proveedor contra la BD local.',
)]
class SyncProviderProductsCommand extends Command
{
    public function __construct(
        private readonly CommunicationCatalogSyncService $syncService,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('environment-id', null, InputOption::VALUE_REQUIRED, 'ID del Environment a sincronizar');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Código del proveedor (ETECSA, DTONE)', CommunicationProviderEnum::ETECSA->value);
        $this->addOption('sync', null, InputOption::VALUE_NONE, 'Ejecutar de forma síncrona (sin messenger). Sin esta opción despacha el mensaje al bus.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $envId = (int) $input->getOption('environment-id');
        $env = $this->environmentRepository->find($envId);

        if (!$env instanceof Environment) {
            $io->error("Environment con ID {$envId} no encontrado.");

            return Command::FAILURE;
        }

        $providerCode = (string) $input->getOption('provider');
        $provider = CommunicationProviderEnum::tryFrom($providerCode);
        if ($provider === null) {
            $io->error("Proveedor '{$providerCode}' desconocido.");

            return Command::FAILURE;
        }

        if ($input->getOption('sync')) {
            $result = $this->syncService->syncProducts($provider, $env);

            $io->success("Sync de productos completado para {$provider->value} en entorno [{$env->getType()}]");
            $io->table(
                ['Creados', 'Actualizados', 'Omitidos'],
                [[$result->created, $result->updated, $result->skipped]]
            );
        } else {
            $this->bus->dispatch(new SyncProviderProductsMessage($provider->value, $envId));
            $io->success("Mensaje SyncProviderProductsMessage({$provider->value}, {$envId}) despachado al bus.");
        }

        return Command::SUCCESS;
    }
}
