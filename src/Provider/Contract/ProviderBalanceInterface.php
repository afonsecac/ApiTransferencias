<?php

namespace App\Provider\Contract;

interface ProviderBalanceInterface extends CommunicationProviderInterface
{
    public function getPlatformBalance(ProviderContext $context): ProviderBalanceResult;
}
