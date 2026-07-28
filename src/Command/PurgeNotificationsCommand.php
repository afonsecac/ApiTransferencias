<?php

namespace App\Command;

use App\Service\NotificationCenterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications-purge',
    description: 'Purga notificaciones caducadas/fuera de retención y tickets de stream vencidos',
)]
class PurgeNotificationsCommand extends Command
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('retention-days', null, InputOption::VALUE_REQUIRED, 'Días de retención de notificaciones', 90)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo contar, sin borrar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $retentionDays = (int) $input->getOption('retention-days');
        $dryRun = (bool) $input->getOption('dry-run');

        $result = $this->notificationCenter->purge($retentionDays, $dryRun);

        if ($dryRun) {
            $io->note(sprintf(
                'Se purgarían %d notificación(es) y %d ticket(s) de stream (retención: %d días).',
                $result['notifications'],
                $result['tickets'],
                $retentionDays,
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Purgadas %d notificación(es) y %d ticket(s) de stream (retención: %d días).',
            $result['notifications'],
            $result['tickets'],
            $retentionDays,
        ));

        return Command::SUCCESS;
    }
}
