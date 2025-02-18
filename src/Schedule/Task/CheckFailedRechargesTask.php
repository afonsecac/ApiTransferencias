<?php

namespace App\Schedule\Task;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("*/2 * * * *", "America/Havana")]
class CheckFailedRechargesTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    )
    {

    }

    public function __invoke(): void
    {
        $sales = $this->em->getRepository(CommunicationSaleInfo::class)->findBy([
            'state' => CommunicationStateEnum::FAILED,
        ], [
            'id' => 'DESC',
        ]);
        $isUpdated = false;
        foreach ($sales as $sale) {
            $sale->setState(CommunicationStateEnum::PENDING);
            $isUpdated = true;
        }
        if ($isUpdated) {
            $this->em->flush();
        }
    }
}