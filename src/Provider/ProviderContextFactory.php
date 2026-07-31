<?php

namespace App\Provider;

use App\Entity\Account;
use App\Entity\CommunicationSaleInfo;
use App\Enums\CommunicationProviderEnum;
use App\Provider\Contract\ProviderContext;

final class ProviderContextFactory
{
    public function __construct(
        private readonly ProviderResolver $resolver,
    ) {
    }

    public function forAccount(Account $account, CommunicationProviderEnum $provider, ?string $correlationId = null): ProviderContext
    {
        return new ProviderContext(
            provider: $provider,
            environmentType: $account->getEnvironment()?->getType() ?? 'PROD',
            environmentId: $account->getEnvironment()?->getId(),
            accountId: $account->getId(),
            clientId: $account->getClient()?->getId(),
            correlationId: $correlationId,
        );
    }

    public function forSale(CommunicationSaleInfo $sale): ProviderContext
    {
        $provider = $this->resolver->resolveForSale($sale);
        $tenant = $sale->getTenant();

        return new ProviderContext(
            provider: $provider,
            environmentType: $tenant?->getEnvironment()?->getType() ?? 'PROD',
            environmentId: $tenant?->getEnvironment()?->getId(),
            accountId: $tenant?->getId(),
            clientId: $tenant?->getClient()?->getId(),
            correlationId: $sale->getTransactionId(),
        );
    }

    public function forEnvironmentType(CommunicationProviderEnum $provider, string $environmentType, ?int $environmentId = null): ProviderContext
    {
        return new ProviderContext(
            provider: $provider,
            environmentType: $environmentType,
            environmentId: $environmentId,
        );
    }
}
