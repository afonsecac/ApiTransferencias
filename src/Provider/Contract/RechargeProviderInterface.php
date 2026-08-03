<?php

namespace App\Provider\Contract;

interface RechargeProviderInterface extends CommunicationProviderInterface
{
    public function recharge(ProviderContext $context, RechargeRequest $request): ProviderDispatchResult;

    public function fetchRechargeStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult;
}
