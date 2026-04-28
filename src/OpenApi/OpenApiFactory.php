<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;

class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly DashboardOpenApiBuilder $builder,
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        return $this->builder->build($this->decorated->__invoke($context));
    }
}
