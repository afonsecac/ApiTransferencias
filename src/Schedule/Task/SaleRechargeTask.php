<?php

namespace App\Schedule\Task;

use App\Repository\CommunicationSaleRechargeRepository;
use App\Service\CommunicationSaleService;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("*/5 * * * *", "America/Havana")]
class SaleRechargeTask
{
    public function __construct(
        private readonly CommunicationSaleRechargeRepository $rechargeRepository,
        private readonly CommunicationSaleService $saleService,
    ) {
    }

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(): void
    {
        $reserves = $this->rechargeRepository->getCurrentActivePromotionsReserves();
        $this->saleService->activateReservedSales($reserves);
    }
}
