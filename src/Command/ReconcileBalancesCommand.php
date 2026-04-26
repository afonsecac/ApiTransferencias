<?php

namespace App\Command;

use App\Service\BalanceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reconcile-balances',
    description: 'Finds completed sales without a balance record and creates the missing balance entries.',
)]
class ReconcileBalancesCommand extends Command
{
    public function __construct(
        private readonly BalanceService $balanceService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->balanceService->reconcileMissingBalances();

        if ($count === 0) {
            $io->success('All completed sales have balance records. Nothing to reconcile.');
        } else {
            $io->success("Reconciled {$count} missing balance(s).");
        }

        return Command::SUCCESS;
    }
}
