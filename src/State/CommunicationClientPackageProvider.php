<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\OutputPaginationPackage;

class CommunicationClientPackageProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new OutputPaginationPackage(0, 0, 0, []);
    }
}
