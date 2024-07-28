<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CommunicationSaleProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly CommunicationSaleService $saleService,

    )
    {
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \App\Exception\MyCurrentException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $communicationSale = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($communicationSale instanceof CommunicationSaleInfo && $communicationSale->getState() === CommunicationStateEnum::PENDING) {
            $communicationSale = $this->saleService->checkSaleInfo($communicationSale->getId());
        }

        return $communicationSale;
    }
}
