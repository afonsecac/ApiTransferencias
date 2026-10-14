<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\BrandDetails;

/**
 * Respuesta de los endpoints de marcas (estructura de presentación, no es de entrada).
 */
class BrandOutDto
{
    /**
     * @param array{id: int, slug: string, name: string, isPlatform: bool}|null $seller
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public bool $isActive,
        public ?array $seller,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(BrandDetails $details): self
    {
        $brand = $details->brand;
        $seller = $details->seller;

        return new self(
            (int) $brand->id(),
            $brand->slug()->value(),
            $brand->name(),
            $brand->isActive(),
            $seller === null ? null : ['id' => (int) $seller->id(), 'slug' => $seller->slug()->value(), 'name' => $seller->name(), 'isPlatform' => $seller->isPlatform()],
            $brand->createdAt()->format(DATE_ATOM),
            $brand->updatedAt()->format(DATE_ATOM),
        );
    }
}
