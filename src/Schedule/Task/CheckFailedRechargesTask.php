<?php

namespace App\Schedule\Task;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("*/2 * * * *", "America/Havana")]
class CheckFailedRechargesTask
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        try {
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
        } catch (\Throwable $e) {
            $this->logger->warning('CheckFailedRechargesTask: DB temporarily unavailable, will retry on next tick. ' . $e->getMessage());
        }
    }
}