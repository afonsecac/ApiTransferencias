<?php

namespace App\Command\Provider;

use App\Service\Provider\ProviderCatalogRefreshService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pensado para cron externo (fuera de este repo), cada 6 horas desde
 * medianoche hora de Cuba (America/Havana): 00:00, 06:00, 12:00, 18:00.
 * Ejemplo de línea de crontab (ajustando el offset de Cuba a la zona
 * horaria del servidor, o usando CRON_TZ=America/Havana si el cron lo
 * soporta):
 *
 *   CRON_TZ=America/Havana
 *   0 0,6,12,18 * * * php bin/console app:provider:refresh-catalogs
 *
 * Solo actualiza lo que cambió de verdad — ver
 * ProviderCatalogRefreshService para el detalle de por qué esto no es
 * automático con Doctrine (el callback PreFlush de CommunicationPricePackage).
 */
#[AsCommand(
    name: 'app:provider:refresh-catalogs',
    description: 'Refresca el catálogo de todos los proveedores ya sincronizados y propaga cambios reales a los CommunicationPricePackage auto-gestionados.',
)]
class RefreshProviderCatalogsCommand extends Command
{
    public function __construct(
        private readonly ProviderCatalogRefreshService $refreshService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->refreshService->refreshAll();

        if ($result->pairsFailed > 0) {
            $io->warning(sprintf(
                '%d par(es) entorno/proveedor fallaron durante el refresco (ver log del canal "provider").',
                $result->pairsFailed,
            ));
        }

        $io->success(sprintf(
            'Refresco completado: %d pares procesados, %d productos con cambios reales, %d paquetes de precio actualizados.',
            $result->pairsProcessed,
            $result->productsChanged,
            $result->pricePackagesUpdated,
        ));

        return $result->pairsFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
