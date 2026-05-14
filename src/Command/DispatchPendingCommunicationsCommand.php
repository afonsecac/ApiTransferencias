<?php

namespace App\Command;

use App\Exception\MyCurrentException;
use App\Service\CommunicationsDispatchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dispatch-pending-communications',
    description: 'Encola en RabbitMQ las ventas que quedaron pendientes mientras el dispatch estaba deshabilitado.',
)]
class DispatchPendingCommunicationsCommand extends Command
{
    public function __construct(
        private readonly CommunicationsDispatchService $dispatchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->dispatchService->dispatchPending();
        } catch (MyCurrentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($result['total'] === 0) {
            $io->success('No hay ventas pendientes de despacho.');
            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Despachadas %d recarga(s) y %d venta(s) de paquete. Total: %d.',
            $result['recharges'],
            $result['packages'],
            $result['total'],
        ));

        return Command::SUCCESS;
    }
}
