<?php

namespace App\Schedule\Task;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Devuelve a PENDING las ventas FAILED recientes que aún esperan al proveedor para que la consulta
 * horaria las vuelva a mirar. Acotada (COMR-259): solo state_process Pending, de las últimas
 * MAX_AGE_HOURS horas y como mucho BATCH_LIMIT por ejecución, para no cargar el histórico en memoria
 * ni bloquear saldo de ventas que ya no se van a consultar.
 */
#[AsCronTask("*/2 * * * *", "America/Havana")]
class CheckFailedRechargesTask
{
    public const BATCH_LIMIT = 100;
    public const MAX_AGE_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        try {
            $sales = $this->em->getRepository(CommunicationSaleInfo::class)->createQueryBuilder('s')
                ->where('s.state = :state')
                ->andWhere('s.stateProcess = :stateProcess')
                ->andWhere('s.createdAt >= :since')
                ->setParameter('state', CommunicationStateEnum::FAILED)
                ->setParameter('stateProcess', CommunicationStateEnum::PENDING->value)
                ->setParameter('since', new \DateTimeImmutable(sprintf('-%d hours', self::MAX_AGE_HOURS)))
                ->orderBy('s.id', 'DESC')
                ->setMaxResults(self::BATCH_LIMIT)
                ->getQuery()
                ->getResult();
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