<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use App\Service\CommunicationSaleService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CreateSaleInfoProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommunicationSaleService $saleService,
    )
    {
    }

    /**
     * @inheritDoc
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return CommunicationSaleInfo|null
     * @throws MyCurrentException
     * @throws \JsonException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommunicationSaleInfo | null
    {
        $communicationSale = null;
        if ($data instanceof CommunicationSaleRecharge) {
            $communicationSale = $this->saleService->processRecharge($data);
        } else if ($data instanceof CommunicationSalePackage) {
            $communicationSale = $this->saleService->executeSale($data);
        }
        return $communicationSale;
    }
}
