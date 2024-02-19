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

#[AsCommand('app:takeOther:command', 'Take other values from ET')]
class TakeOtherCommand extends Command
{
    public function __construct(
        private readonly TakeProductService $productService,
    )
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('dry-execute-take-other', null, InputOption::VALUE_NONE, 'Execute Take Nationalities, Province, Offices');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $io->note('Execute take other info');
            $this->productService->takeOtherData();

            $io->success('Completed take other info');

            return Command::SUCCESS;
        } catch (TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|DecodingExceptionInterface|ClientExceptionInterface|\Exception $exc) {
            $io->error($exc->getMessage());
            return Command::FAILURE;
        }
    }

}
