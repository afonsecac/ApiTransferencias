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
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     */
    public function __invoke(SaleRechargeMessage $message): void {
        $this->saleService->invokeRechargeCommunication($message->getSale(), $message->getSaleId());
    }
}