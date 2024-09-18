<?php

namespace App\MessageHandler;

use App\Message\SalePackageMessage;
use App\Service\CommunicationSaleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SalePackageMessageHandler
{
    public function __construct(private readonly CommunicationSaleService $saleService)
    {

    }

    /**
     * @param \App\Message\SalePackageMessage $message
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __invoke(SalePackageMessage $message): void
    {
        $this->saleService->executeNewSaleInfo($message->getSaleId());
    }

}