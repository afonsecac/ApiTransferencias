<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileRepository;

final class ListFiscalProfiles
{
    public function __construct(private readonly FiscalProfileRepository $profiles)
    {
    }

    /**
     * @return list<FiscalProfile>
     */
    public function __invoke(): array
    {
        return $this->profiles->findAll();
    }
}
