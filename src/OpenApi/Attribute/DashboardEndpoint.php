<?php

namespace App\OpenApi\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class DashboardEndpoint
{
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $tag = null,
        public ?string $requestDto = null,
        public ?string $responseDto = null,
        public ?string $itemDto = null,
        public ?int $responseStatusCode = null,
        public bool $responseIsArray = false,
    ) {}
}
