<?php

namespace App\MessageHandler;

use App\Message\CheckSaleMessage;
use App\Service\CommunicationSaleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckSaleMessageHandler
{

    public function __construct(private readonly CommunicationSaleService $communicationSaleService)
    {
    }

    /**
     * @param \App\Message\CheckSaleMessage $message
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(CheckSaleMessage $message): void
    {
        $this->communicationSaleService->checkSaleInfo($message->getSaleId());
    }

}