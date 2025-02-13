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
        private readonly EntityManagerInterface $entityManager,
    )
    {

    }

    public function __invoke(): void
    {
        $sales = $this->entityManager->getRepository(CommunicationSaleInfo::class)->find([
            'state' => CommunicationStateEnum::FAILED,
        ], [
            'createdAt' => 'DESC',
        ]);
        $isUpdated = false;
        foreach ($sales as $sale) {
            $sale->setState(CommunicationStateEnum::PENDING);
            $isUpdated = true;
        }
        if ($isUpdated) {
            $this->entityManager->flush();
        }
    }
}