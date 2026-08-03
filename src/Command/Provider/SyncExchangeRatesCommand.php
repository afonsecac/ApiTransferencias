<?php

namespace App\Command\Provider;

use App\Service\Provider\CurrencyExchangeRateSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pensado para cron externo (fuera de este repo), p.ej. una vez al día
 * hábil — Frankfurter solo publica una tasa nueva por día hábil (BCE), así
 * que correrlo con más frecuencia no aporta nada nuevo.
 */
#[AsCommand(
    name: 'app:provider:sync-exchange-rates',
    description: 'Sincroniza el histórico de tasas de cambio (Frankfurter, base EUR) usado por ClientCatalogImportService.',
)]
class SyncExchangeRatesCommand extends Command
{
    public function __construct(
        private readonly CurrencyExchangeRateSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->syncService->sync();
        } catch (\Throwable $e) {
            $io->error('Fallo al sincronizar tasas de cambio: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Tasas de cambio sincronizadas: %d filas nuevas (base %s, fecha %s).',
            $result->created,
            $result->baseCurrency,
            $result->rateDate,
        ));

        return Command::SUCCESS;
    }
}
