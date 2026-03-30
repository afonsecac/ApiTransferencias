<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Enums\RebusStatusEnum;
use App\Service\TransferCalculatorService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TransferProvider implements ProviderInterface
{

    public function __construct(
        private readonly Security $security,
        private readonly TransferCalculatorService $service,
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
    ) {
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if ($user instanceof Account) {
            $transfer = $this->itemProvider->provide($operation, $uriVariables, $context);
            if ($transfer instanceof Transfer) {
                $stateId = $transfer->getStatusId();
                if ($stateId !== RebusStatusEnum::Completed->value && $stateId !== RebusStatusEnum::Rejected->value) {
                    return $this->service->getTransferData($transfer, $user);
                }
                return $transfer;
            }

        }

        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }

}
