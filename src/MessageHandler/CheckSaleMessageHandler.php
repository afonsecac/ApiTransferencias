<?php

namespace App\MessageHandler;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckSaleMessageHandler
{
    public function __construct(
        private readonly CommunicationSaleService $communicationSaleService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws MyCurrentException
     */
    public function __invoke(CheckSaleMessage $message): void
    {
        $saleId = $message->getSaleId();
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($saleId);

        if ($sale === null) {
            $this->logger->warning("CheckSale: sale {$saleId} not found, discarding message.");
            return;
        }

        // Si la venta ya tiene un estado final o está reservada, no hay nada que hacer
        $skipStates = [CommunicationStateEnum::COMPLETED, CommunicationStateEnum::REJECTED, CommunicationStateEnum::RESERVED];
        if (in_array($sale->getState(), $skipStates, true)) {
            return;
        }

        // Si la transacción aún no fue enviada a ETECSA, reintentar más tarde
        $stateProcess = $sale->getStateProcess();
        if ($stateProcess === null
            || $stateProcess === CommunicationStateEnum::CREATED->value
            || $stateProcess === 'SENDING'
        ) {
            $this->logger->info("CheckSale: sale {$saleId} not yet sent to provider (stateProcess={$stateProcess}), will retry.");
            throw new MyCurrentException('501', 'Sale not yet sent, retry later');
        }

        $this->communicationSaleService->checkStatusSaleInfo($saleId);

        // Refrescar estado después del check
        $this->em->refresh($sale);

        if ($sale->getState() === CommunicationStateEnum::PENDING) {
            throw new MyCurrentException('501', 'Check again');
        }
        if ($sale->getState() === CommunicationStateEnum::FAILED) {
            throw new MyCurrentException('501', 'Failed to process the check sale');
        }
    }
}
