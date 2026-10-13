<?php

namespace App\MessageHandler;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
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

        // Aún no enviada al proveedor: no hay nada que consultar. No se lanza para que Messenger reintente (eso
        // creaba cadenas sin tope): el cron solo reclama ventas ya enviadas, y esta se consultará cuando lo esté.
        if (!$sale->wasSentToProvider()) {
            $this->logger->info("CheckSale: sale {$saleId} not yet sent to provider (stateProcess={$sale->getStateProcess()}), nothing to check.");
            return;
        }

        // Una sola consulta. Si la venta sigue pendiente (o es incierta o FAILED), no se lanza ninguna excepción:
        // checkStatusOrder() ya programó next_check_at con la espera creciente y el cron la volverá a reclamar.
        $this->communicationSaleService->checkStatusSaleInfo($saleId);
    }
}
