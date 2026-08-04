<?php

namespace App\Command\StagingSync;

use App\Enums\StagingSyncStatusEnum;
use App\Service\StagingSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Único punto por el que scripts/sync-prod-to-staging.sh reporta su estado
 * a la app — al inicio (RUNNING) y al final (SUCCESS/FAILED), sea que lo
 * haya disparado el cron mensual o un admin desde el dashboard. Ver
 * App\Service\StagingSyncService::report().
 */
#[AsCommand(
    name: 'app:staging-sync:report',
    description: 'Registra el estado de una corrida de scripts/sync-prod-to-staging.sh',
)]
class ReportStagingSyncCommand extends Command
{
    public function __construct(
        private readonly StagingSyncService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('status', InputArgument::REQUIRED, 'RUNNING, SUCCESS o FAILED')
            ->addOption('triggered-by', null, InputOption::VALUE_REQUIRED, "Email del admin, o 'cron'")
            ->addOption('error', null, InputOption::VALUE_REQUIRED, 'Mensaje de error (solo con status=FAILED)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $status = StagingSyncStatusEnum::tryFrom((string) $input->getArgument('status'));
        if ($status === null) {
            $io->error('status debe ser RUNNING, SUCCESS o FAILED.');

            return Command::INVALID;
        }

        $this->service->report(
            $status,
            $input->getOption('triggered-by'),
            $input->getOption('error'),
        );

        $io->success("Corrida registrada: {$status->value}.");

        return Command::SUCCESS;
    }
}
