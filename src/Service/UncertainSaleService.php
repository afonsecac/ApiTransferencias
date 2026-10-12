<?php

namespace App\Service;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Enums\ProviderOutcomeEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Provider\TransactionStatus;
use App\Repository\CommunicationSaleInfoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Resolución de las ventas inciertas (COMR-257/258): las que el proveedor no conoce o que se colgaron en
 * SENDING. Su importe sigue comprometido hasta que una persona decide, con motivo, o el proveedor las confirma.
 */
class UncertainSaleService
{
    public const RESOLUTION_COMPLETED = 'completed';
    public const RESOLUTION_REJECTED = 'rejected';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BalanceService $balanceService,
        private readonly HistoricalSaleService $historicalSaleService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Las más antiguas primero: son las que llevan más tiempo con el importe comprometido.
     *
     * @return array{items: list<CommunicationSaleInfo>, hasNext: bool}
     */
    public function list(int $page, int $limit): array
    {
        $repository = $this->em->getRepository(CommunicationSaleInfo::class);
        \assert($repository instanceof CommunicationSaleInfoRepository);
        $items = $repository->findUncertain($page * $limit, $limit + 1);

        return [
            'items' => \array_slice($items, 0, $limit),
            'hasNext' => \count($items) > $limit,
        ];
    }

    /**
     * Encola otra consulta de estado por nuestro transactionId. Nunca reenvía la venta.
     *
     * @throws MyCurrentException
     */
    public function recheck(int $saleId): void
    {
        $sale = $this->uncertainSale($saleId);
        $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()));
    }

    /**
     * @throws MyCurrentException
     */
    public function resolve(int $saleId, string $resolution, string $reason, ?string $resolvedBy): CommunicationSaleInfo
    {
        if (!\in_array($resolution, [self::RESOLUTION_COMPLETED, self::RESOLUTION_REJECTED], true)) {
            throw new MyCurrentException('INVALID_RESOLUTION', 'Resolution must be "completed" or "rejected"', 400);
        }
        $sale = $this->uncertainSale($saleId);
        $tenant = $sale->getTenant();
        if ($resolution === self::RESOLUTION_COMPLETED && $tenant === null) {
            throw new MyCurrentException('SALE_WITHOUT_ACCOUNT', 'The sale has no account to debit', 409);
        }

        $completed = $resolution === self::RESOLUTION_COMPLETED;
        $finalState = $completed ? CommunicationStateEnum::COMPLETED : CommunicationStateEnum::REJECTED;
        $table = $this->em->getClassMetadata(CommunicationSaleInfo::class)->getTableName();

        // Condicional: si otro administrador o el propio proveedor la resolvió entre la lectura y ahora, no se pisa.
        $affected = $this->em->getConnection()->executeStatement(
            "UPDATE {$table} SET state = :final, state_process = :final WHERE id = :id AND state = :pending AND state_process = :uncertain",
            [
                'final' => $finalState->value,
                'id' => $saleId,
                'pending' => CommunicationStateEnum::PENDING->value,
                'uncertain' => CommunicationSaleInfo::STATE_PROCESS_UNCERTAIN,
            ]
        );
        if ($affected === 0) {
            throw new MyCurrentException('SALE_NOT_UNCERTAIN', 'The sale is no longer uncertain', 409);
        }
        $this->em->refresh($sale);

        $envelope = TransactionStatus::internal(
            $completed ? ProviderOutcomeEnum::COMPLETED : ProviderOutcomeEnum::REJECTED,
            'INTERNAL_MANUAL_RESOLUTION',
            sprintf('Resolved manually as %s by %s: %s', $resolution, $resolvedBy ?? 'unknown user', $reason),
        );
        $sale->setTransactionStatus($envelope);
        $sale->clearNextCheck();
        if ($completed) {
            $this->balanceService->createSaleBalance($tenant, $sale);
        }
        $this->historicalSaleService->createHistoricalCommunication($saleId, $finalState, $envelope);
        $this->em->flush();

        return $sale;
    }

    /**
     * @throws MyCurrentException
     */
    private function uncertainSale(int $saleId): CommunicationSaleInfo
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);
        if ($sale === null) {
            throw new MyCurrentException('SALE_NOT_FOUND', 'Sale not found', 404);
        }
        if (!$sale->isUncertain() || $sale->getState() !== CommunicationStateEnum::PENDING) {
            throw new MyCurrentException('SALE_NOT_UNCERTAIN', 'The sale is not uncertain', 409);
        }

        return $sale;
    }
}
