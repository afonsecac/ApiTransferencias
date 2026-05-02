<?php

namespace App\Command;

use App\Entity\CommunicationSaleRecharge;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:activate-reserved-sales',
    description: 'Activates reserved sales whose promotion has started.',
)]
class ActivateReservedSalesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationSaleService $saleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var \App\Repository\CommunicationSaleRechargeRepository $repo */
        $repo = $this->em->getRepository(CommunicationSaleRecharge::class);
        $reserved = $repo->getCurrentActivePromotionsReserves();

        if (empty($reserved)) {
            $io->success('No reserved sales to activate.');
            return Command::SUCCESS;
        }

        $activated = $this->saleService->activateReservedSales($reserved);

        $io->success("Activated {$activated} reserved sale(s) out of " . count($reserved) . " found.");

        return Command::SUCCESS;
    }
}
