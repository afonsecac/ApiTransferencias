<?php

namespace App\MessageHandler;

use App\Message\SaleRechargeMessage;
use App\Service\CommunicationSaleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SaleRechargeMessageHandler
{
    public function __construct(private readonly CommunicationSaleService $saleService) {}

    /**
     * @param \App\Message\SaleRechargeMessage $message
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    public function __invoke(SaleRechargeMessage $message): void {
        $this->saleService->invokeRechargeCommunication($message->getSale(), $message->getSaleId());
    }
}