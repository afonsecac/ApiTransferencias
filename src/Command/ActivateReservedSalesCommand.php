<?php

namespace App\Command;

use App\Entity\CommunicationPromotions;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\CommunicationStateEnum;
use App\Message\SaleRechargeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:activate-reserved-sales',
    description: 'Activates reserved sales whose promotion has started.',
)]
class ActivateReservedSalesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable('now');

        // Buscar ventas reservadas cuya promoción ya comenzó
        $reserved = $this->em->getRepository(CommunicationSaleRecharge::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.promotion', 'p')
            ->where('s.state = :reserved')
            ->andWhere('s.stateProcess = :created')
            ->andWhere('p.startAt <= :now')
            ->setParameter('reserved', CommunicationStateEnum::RESERVED->value)
            ->setParameter('created', CommunicationStateEnum::CREATED->value)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if (empty($reserved)) {
            $io->success('No reserved sales to activate.');
            return Command::SUCCESS;
        }

        $activated = 0;
        foreach ($reserved as $sale) {
            // Verificar que la promoción siga activa (no haya pasado endAt)
            $promotion = $sale->getPromotion();
            if ($promotion instanceof CommunicationPromotions && $promotion->getEndAt() < $now) {
                $sale->setState(CommunicationStateEnum::REJECTED);
                $sale->setStateProcess(CommunicationStateEnum::REJECTED->value);
                $sale->setTransactionStatus(['result' => ['message' => 'Promotion expired before activation']]);
                $this->logger->info("Reserved sale {$sale->getId()} rejected: promotion expired.");
                continue;
            }

            // Activar: pasar a PENDING para que el worker la procese
            $sale->setState(CommunicationStateEnum::PENDING);
            // stateProcess queda en CREATED para que invokeRechargeCommunication la tome
            $this->em->flush();

            $this->messageBus->dispatch(new SaleRechargeMessage($sale->getId()));
            $activated++;
            $this->logger->info("Reserved sale {$sale->getId()} activated, dispatched to worker.");
        }

        $this->em->flush();
        $io->success("Activated {$activated} reserved sale(s) out of " . count($reserved) . " found.");

        return Command::SUCCESS;
    }
}
