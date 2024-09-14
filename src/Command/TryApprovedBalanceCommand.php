<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BalanceOperation;
use App\Entity\EmailNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:try_approved_balance', description: 'Hello PhpStorm')]
class TryApprovedBalanceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption('execute-configure-option', null, InputOption::VALUE_NONE, 'Execute approbation Balance');
        $this->addArgument('balanceId', InputOption::VALUE_REQUIRED, 'Balance id approved');
        $this->addArgument('amount', InputOption::VALUE_REQUIRED, 'Balance amount approved');
        $this->addArgument('currency', InputOption::VALUE_REQUIRED, 'Balance currency');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Execute try approbation balance');
        try {
            $balanceId = (int)$input->getArgument('balanceId');
            $amount = (float)$input->getArgument('amount');
            $currency = $input->getArgument('currency');

            $balance = $this->em->getRepository(BalanceOperation::class)->find($balanceId);
            if (!is_null($balance)) {
                $account = $balance->getTenant();
                $balance->setTotalAmount($amount);
                $balance->setCurrency($currency);
                $balance->setState('COMPLETED');
                if (!is_null($account)) {
                    $lastNotification = $this->em->getRepository(EmailNotification::class)->getLastNotification($account?->getId());
                    if (!is_null($lastNotification)) {
                        $lastNotification->setBalanceIn($balance);
                        $lastNotification->setActive(false);
                        $lastNotification->setClosedAt(new \DateTimeImmutable('now'));
                    }
                    $notification = new EmailNotification();
                    $notification->setBalanceIn($balance);
                    $notification->setAccount($account);
                    $notification->setActive(true);
                    $this->em->persist($notification);
                }
                $this->em->flush();
                $io->success('Completed approbation balance');
            } else {
                $io->warning('Balance not found');
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
