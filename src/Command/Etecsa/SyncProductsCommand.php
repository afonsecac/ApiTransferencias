<?php

namespace App\Command\Etecsa;

use App\Entity\Environment;
use App\Message\Etecsa\SyncProductsMessage;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaCatalogSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:etecsa:sync-products',
    description: 'Sincroniza el catálogo de productos ETECSA contra la BD local.',
)]
class SyncProductsCommand extends Command
{
    public function __construct(
        private readonly EtecsaCatalogSyncService $syncService,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('environment-id', null, InputOption::VALUE_REQUIRED, 'ID del Environment a sincronizar');
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

        if ($input->getOption('sync')) {
            $result = $this->syncService->syncProducts($env);

            $io->success("Sync de productos completado para entorno [{$env->getType()}]");
            $io->table(
                ['Creados', 'Actualizados', 'Omitidos'],
                [[$result->created, $result->updated, $result->skipped]]
            );
        } else {
            $this->bus->dispatch(new SyncProductsMessage($envId));
            $io->success("Mensaje SyncProductsMessage({$envId}) despachado al bus.");
        }

        return Command::SUCCESS;
    }
}
