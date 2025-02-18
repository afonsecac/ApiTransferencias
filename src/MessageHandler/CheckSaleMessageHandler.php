<?php

namespace App\MessageHandler;

use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Message\CheckSaleMessage;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckSaleMessageHandler
{

    public function __construct(private readonly CommunicationSaleService $communicationSaleService, private readonly EntityManagerInterface $em)
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
     * @throws \App\Exception\MyCurrentException
     */
    public function __invoke(CheckSaleMessage $message): void
    {
        $this->communicationSaleService->checkStatusSaleInfo($message->getSaleId());
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($message->getSaleId());
        if (is_null($sale) || $sale->getState() === CommunicationStateEnum::PENDING) {
            throw new MyCurrentException('501', 'Check again');
        }
        if ($sale->getState() === CommunicationStateEnum::FAILED) {
            throw new MyCurrentException('501', 'Failed to process the check sale');
        }
    }

}