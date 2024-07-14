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

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $communicationSale = $this->itemProvider->provide($operation, $uriVariables, $context);

        // && $communicationSale->getState() === CommunicationStateEnum::PENDING
        if ($communicationSale instanceof CommunicationSaleInfo && $communicationSale->getState() === CommunicationStateEnum::PENDING) {
            $communicationSale = $this->saleService->checkSaleInfo($communicationSale->getId());
        }

        return $communicationSale;
    }
}
