<?php

namespace App\Provider\Contract;

interface PackageSaleProviderInterface extends CommunicationProviderInterface
{
    public function sellPackage(ProviderContext $context, PackageSaleRequest $request): ProviderDispatchResult;

    public function fetchPackageSaleStatus(ProviderContext $context, ProviderStatusQuery $query): ProviderStatusResult;
}
