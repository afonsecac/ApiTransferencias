<?php

namespace App\Schedule\Task;


use App\Enums\CommunicationStateEnum;
use App\Repository\CommunicationSaleRechargeRepository;
use App\Service\CommunicationSaleService;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("0 0 * * *", "America/Havana")]
class SaleRechargeTask
{
    public function __construct(private readonly CommunicationSaleRechargeRepository $rechargeRepository, private readonly CommunicationSaleService $saleService) {

    }

    /**
     * @return void
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(): void
    {
        $reserves = $this->rechargeRepository->getCurrentActivePromotionsReserves();
        foreach ($reserves as $saleRecharge) {
            $this->saleService->tryAgainWithTransaction($saleRecharge->getId());
        }
    }
}