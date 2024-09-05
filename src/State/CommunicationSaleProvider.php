<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationStateEnum;
use App\Service\CommunicationSaleService;
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
     * @param \ApiPlatform\Metadata\Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|null
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $communicationSale = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($communicationSale instanceof CommunicationSaleInfo && $communicationSale->getState() === CommunicationStateEnum::PENDING) {
            $currentDate = new \DateTimeImmutable('now');
            $updatedAt = $communicationSale->getUpdatedAt();
            $updatedAtDiff = $currentDate->diff($updatedAt);
            if ($updatedAtDiff->i >= 1) {
                $communicationSale = $this->saleService->checkSaleInfo($communicationSale->getId());
            }
        }

        return $communicationSale;
    }
}
