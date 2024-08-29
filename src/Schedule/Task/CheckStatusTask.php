<?php

namespace App\Schedule\Task;

use App\Service\CommunicationSaleService;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask("* * * * *", "America/Havana")]
class CheckStatusTask
{
    public function __construct(
        private readonly CommunicationSaleService $communicationSaleService,
    ) {}

    /**
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function __invoke(): void
    {
        $this->communicationSaleService->unprocessed();
    }


}