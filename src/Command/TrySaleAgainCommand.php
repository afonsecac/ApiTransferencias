<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\NotificationDraft;
use App\Entity\CommunicationSaleRecharge;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\NotificationTypeEnum;
use App\Service\CommunicationSaleService;
use App\Service\NotificationCenterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:try_sale_again', description: 'Hello PhpStorm')]
class TrySaleAgainCommand extends Command
{
    public function __construct(
        protected readonly CommunicationSaleService $service,
        private readonly EntityManagerInterface $em,
        private readonly NotificationCenterService $notificationCenter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('execute-configure-option',null, InputOption::VALUE_NONE, 'Execute Try Product Again');
        $this->addArgument('saleId', InputOption::VALUE_REQUIRED, 'Sale try again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Execute try again sale');
        try {
            $saleId = (int) $input->getArgument('saleId');
            $this->service->tryAgainWithTransaction($saleId);

            // Que un operador/cron tenga que forzar un reintento manual ya es
            // una señal para ROLE_ADMIN, agrupada por hora para no inundar la
            // bandeja con un reintento por sale.
            $recharge = $this->em->getRepository(CommunicationSaleRecharge::class)->find($saleId);
            if ($recharge !== null) {
                $this->notificationCenter->bumpGroup(
                    'sale_retry:' . (new \DateTimeImmutable())->format('Y-m-d-H'),
                    NotificationAudienceEnum::ROLE,
                    new NotificationDraft(
                        type: NotificationTypeEnum::SALE_RETRY_EXHAUSTED,
                        title: 'Reintentos de venta forzados manualmente',
                        level: NotificationLevelEnum::WARNING,
                        link: '/apps/sales/' . $saleId,
                        data: ['lastSaleId' => $saleId],
                        environmentId: $recharge->getTenant()?->getEnvironment()?->getId(),
                    ),
                    targetRole: 'ROLE_ADMIN',
                );
            }

            $io->success('Completed try again sale');

            return Command::SUCCESS;
        } catch (\Exception $exc) {
            $io->error($exc->getMessage());
            return Command::FAILURE;
        }
    }
}
