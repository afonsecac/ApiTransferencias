<?php

namespace App\Command;

use App\Entity\CommunicationSaleInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rellena communication_sale_info.access_token para ventas creadas antes de
 * que el campo existiera (migración Version20261010120000) — PrePersist
 * solo lo asigna en inserts nuevos, así que esas filas históricas se
 * quedaban con NULL y el botón "Ver comprobante" del dashboard nunca podía
 * mostrarse para ellas. Idempotente: puede correrse varias veces, solo
 * toca filas con access_token NULL.
 */
#[AsCommand(
    name: 'app:sale:backfill-access-token',
    description: 'Rellena el token de acceso del comprobante en ventas que no lo tienen',
)]
class BackfillSaleAccessTokenCommand extends Command
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $qb = $this->em->getRepository(CommunicationSaleInfo::class)->createQueryBuilder('s')
            ->where('s.accessToken IS NULL')
            ->orderBy('s.id', 'ASC');

        $filled = 0;

        // Cada venta que se rellena sale del WHERE accessToken IS NULL, así
        // que el lote siguiente se vuelve a pedir desde el principio (no se
        // avanza un offset: avanzarlo saltaría filas que siguen pendientes).
        while (true) {
            /** @var CommunicationSaleInfo[] $batch */
            $batch = (clone $qb)->setMaxResults(self::BATCH_SIZE)->getQuery()->getResult();

            if ($batch === []) {
                break;
            }

            foreach ($batch as $sale) {
                $sale->ensureAccessToken();
                $filled++;
            }

            $this->em->flush();
            $this->em->clear();
        }

        $io->success(sprintf('%d venta(s) actualizadas con un nuevo token de acceso.', $filled));

        return Command::SUCCESS;
    }
}
