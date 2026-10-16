<?php

declare(strict_types=1);

namespace App\Market\Geografia\Application;

use App\Market\Geografia\Domain\GeographyRepository;

final class ListProvinces
{
    public function __construct(private readonly GeographyRepository $geography)
    {
    }

    /** @return list<ProvinceDetails> */
    public function __invoke(): array
    {
        $counts = $this->geography->municipalityCounts();

        return array_map(
            static fn ($p) => new ProvinceDetails($p, $counts[$p->id()] ?? 0),
            $this->geography->provinces(),
        );
    }
}
