<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\ProductRepository;

final class GetMeasureSettings
{
    public function __construct(
        private readonly MeasureSettings $settings,
        private readonly ProductRepository $products,
    ) {
    }

    public function __invoke(): MeasureSettingsDetails
    {
        return new MeasureSettingsDetails(
            $this->settings->weightBase(),
            $this->settings->volumeBase(),
            $this->settings->volumetricDivisor(),
            $this->products->hasPhysicalProducts(),
        );
    }
}
