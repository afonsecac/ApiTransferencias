<?php

namespace App\Command\Etecsa;

use App\Entity\Environment;
use App\Message\Etecsa\SyncCatalogsMessage;
use App\MessageHandler\Etecsa\SyncCatalogsMessageHandler;
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
    name: 'app:etecsa:sync-catalogs',
    description: 'Sincroniza los catálogos ETECSA (nationalities, provinces, offices) contra la BD local.',
)]
class SyncCatalogsCommand extends Command
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
            $nat = $this->syncService->syncNationalities($env);
            $prv = $this->syncService->syncProvinces($env);
            $off = $this->syncService->syncOffices($env);

            $io->success("Sync completado para entorno [{$env->getType()}]");
            $io->table(
                ['Catálogo', 'Creados', 'Actualizados', 'Omitidos'],
                [
                    ['Nationalidades', $nat->created, $nat->updated, $nat->skipped],
                    ['Provincias',     $prv->created, $prv->updated, $prv->skipped],
                    ['Oficinas',       $off->created, $off->updated, $off->skipped],
                ]
            );
        } else {
            $this->bus->dispatch(new SyncCatalogsMessage($envId));
            $io->success("Mensaje SyncCatalogsMessage({$envId}) despachado al bus.");
        }

        return Command::SUCCESS;
    }
}
