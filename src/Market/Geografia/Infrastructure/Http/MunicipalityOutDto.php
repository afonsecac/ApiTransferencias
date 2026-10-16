<?php

declare(strict_types=1);

namespace App\Market\Geografia\Infrastructure\Http;

use App\Market\Geografia\Application\MunicipalityDetails;

/**
 * Respuesta de municipios. `active` es el estado propio; `selectable` incluye el de su provincia.
 */
class MunicipalityOutDto
{
    /**
     * @param array{id: int, name: string, active: bool} $province
     */
    public function __construct(
        public int $id,
        public string $code,
        public ?string $oneiCode,
        public string $name,
        public bool $active,
        public bool $selectable,
        public array $province,
    ) {
    }

    public static function from(MunicipalityDetails $details): self
    {
        $m = $details->municipality;
        $p = $details->province;

        return new self(
            (int) $m->id(),
            $m->code(),
            $m->oneiCode(),
            $m->name(),
            $m->isActive(),
            $details->selectable,
            ['id' => (int) $p->id(), 'name' => $p->name(), 'active' => $p->isActive()],
        );
    }
}
