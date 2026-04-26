<?php

namespace App\Command;

use App\Service\TakeProductService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand('app:takeProduct', 'Takes products of environments')]
class TakeProductCommand extends Command
{
    public function __construct(
        private readonly TakeProductService $productService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-execute-take-product', null, InputOption::VALUE_NONE, 'Execute Take Product');
        $this->addOption('env-type', null, InputOption::VALUE_REQUIRED, 'Environment type (e.g. TEST, PROD)', 'PROD');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {

            /** @var string $env */
            $env = $input->getOption('env-type');
            $io->note('Execute take product for environment: ' . $env);
            $this->productService->takeProduct($env);

            $io->success('Completed take product');

            return Command::SUCCESS;
        } catch (ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|\Exception $exc) {
            $io->error($exc->getMessage());

            return Command::FAILURE;
        }
    }

}
