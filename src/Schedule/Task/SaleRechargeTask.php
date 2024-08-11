<?php

namespace App\Schedule\Task;


use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("*/15 * * * *", "America/Havana")]
class SaleRechargeTask
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CommunicationSaleService $saleService) {

    }

    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }
}