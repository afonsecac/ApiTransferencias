<?php

namespace App\Command;

use App\Service\TakeProductService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:configureProduct:command', 'Configure Product By Client')]
class ConfigureProductCommand extends Command
{

    public function __construct(
        private readonly TakeProductService $productService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute-configure-option',null, InputOption::VALUE_NONE, 'Execute Configure products by client');
        $this->addArgument('productId', InputOption::VALUE_REQUIRED, 'Product of client');
        $this->addArgument('env', InputOption::VALUE_REQUIRED, 'Environment configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Execute configure products');
        try {
            $productId = (int) $input->getArgument('productId');
            $env = $input->getArgument('env');
            $this->productService->configurePackages($productId, $env);

            $io->success('Completed configure products');

            return Command::SUCCESS;
        } catch (\Exception $exc) {
            $io->error($exc->getMessage());
            return Command::FAILURE;
        }
    }
}
