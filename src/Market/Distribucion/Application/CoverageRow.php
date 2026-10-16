<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Application;

use App\Market\Distribucion\Domain\DeliveryCoverage;
use App\Market\Distribucion\Domain\DeliveryMunicipality;

/** Una cobertura con su municipio y lo que el listado avisa: si atiende, si hay empate y si es un respaldo más caro. */
final readonly class CoverageRow
{
    public function __construct(
        public DeliveryCoverage $coverage,
        public ?DeliveryMunicipality $municipality,
        public string $currency,
        public bool $isServing = false,
        public bool $tie = false,
        public bool $backupMoreExpensive = false,
    ) {
    }
}
