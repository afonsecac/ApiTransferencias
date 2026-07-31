<?php

namespace App\Command\Etecsa;

use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderBalanceInterface;
use App\Provider\ProviderContextFactory;
use App\Provider\ProviderRegistry;
use App\Repository\EnvironmentRepository;
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
        private readonly ProviderRegistry $providerRegistry,
        private readonly ProviderContextFactory $providerContextFactory,
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

        $context = $this->providerContextFactory->forEnvironmentType(
            CommunicationProviderEnum::ETECSA,
            $env->getType(),
            $env->getId(),
        );
        $adapter = $this->providerRegistry->getFor(CommunicationProviderEnum::ETECSA, ProviderBalanceInterface::class);
        $balance = $adapter->getPlatformBalance($context);

        $io->success("Saldo para entorno [{$env->getType()}] (ID {$env->getId()})");
        $io->table(['Campo', 'Valor'], [
            ['CUP', number_format($balance->amounts['CUP'] ?? 0.0, 2)],
            ['USD', number_format($balance->amounts['USD'] ?? 0.0, 2)],
            ['Consultado', $balance->fetchedAt->format('Y-m-d H:i:s')],
        ]);

        return Command::SUCCESS;
    }
}
