<?php

namespace App\Command\Etecsa;

use App\Entity\Environment;
use App\Repository\EnvironmentRepository;
use App\Service\Etecsa\EtecsaGatewayClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:etecsa:fetch-balance',
    description: 'Consulta el saldo disponible (CUP y USD) para un entorno ETECSA.',
)]
class FetchBalanceCommand extends Command
{
    public function __construct(
        private readonly EtecsaGatewayClient $client,
        private readonly EnvironmentRepository $environmentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('environment-id', null, InputOption::VALUE_REQUIRED, 'ID del Environment a consultar');
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

        $balance = $this->client->getBalance($env);

        $io->success("Saldo para entorno [{$env->getType()}] (ID {$env->getId()})");
        $io->table(['Campo', 'Valor'], [
            ['CUP', number_format($balance->cupAmount, 2)],
            ['USD', number_format($balance->usdAmount, 2)],
            ['Consultado', $balance->fetchedAt->format('Y-m-d H:i:s')],
        ]);

        return Command::SUCCESS;
    }
}
