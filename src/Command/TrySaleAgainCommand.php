<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CommunicationSaleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:try_sale_again', description: 'Hello PhpStorm')]
class TrySaleAgainCommand extends Command
{
    public function __construct(protected readonly CommunicationSaleService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute-configure-option',null, InputOption::VALUE_NONE, 'Execute Try Product Again');
        $this->addArgument('saleId', InputOption::VALUE_REQUIRED, 'Sale try again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Execute try again sale');
        try {
            $saleId = (int) $input->getArgument('saleId');
            $this->service->tryAgainWithTransaction($saleId);

            $io->success('Completed try again sale');

            return Command::SUCCESS;
        } catch (\Exception $exc) {
            $io->error($exc->getMessage());
            return Command::FAILURE;
        }
    }
}
