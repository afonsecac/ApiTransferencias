<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\SellerDetails;

/**
 * Respuesta de los endpoints de vendedores (estructura de presentación, no es de entrada).
 */
class SellerOutDto
{
    /**
     * @param array{id: int, name: string, types: list<string>}|null $orgUnit
     * @param array{id: int, code: string, name: string}|null $costCenter
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public bool $isPlatform,
        public bool $isActive,
        public ?array $orgUnit,
        public ?array $costCenter,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(SellerDetails $details): self
    {
        $seller = $details->seller;

        return new self(
            (int) $seller->id(),
            $seller->slug()->value(),
            $seller->name(),
            $seller->isPlatform(),
            $seller->isActive(),
            $details->orgUnit === null ? null : ['id' => $details->orgUnit->id, 'name' => $details->orgUnit->name, 'types' => $details->orgUnit->types],
            $details->costCenter === null ? null : ['id' => $details->costCenter->id, 'code' => $details->costCenter->code, 'name' => $details->costCenter->name],
            $seller->createdAt()->format(DATE_ATOM),
            $seller->updatedAt()->format(DATE_ATOM),
        );
    }
}
