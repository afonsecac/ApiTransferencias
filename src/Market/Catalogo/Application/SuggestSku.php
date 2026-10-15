<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\CategoryNotFound;
use App\Market\Catalogo\Domain\CategoryRepository;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Sku;
use App\Market\Catalogo\Domain\SkuSuggestion;

/**
 * Propone el sku de un producto nuevo a partir de su nombre y su categoría (COMR-277): CATEGORÍA-NOMBRE-NNN, con el consecutivo
 * siguiente al más alto ya usado. Solo propone: no guarda nada y la persona puede escribir otro.
 */
final class SuggestSku
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * @param int|null $categoryId la primera categoría del producto; sin ella, el prefijo genérico
     *
     * @throws CategoryNotFound
     * @throws InvalidProduct sin nombre del que sacar una palabra clave
     */
    public function __invoke(string $name, ?int $categoryId): Sku
    {
        if (trim($name) === '') {
            throw new InvalidProduct('Escribe el nombre del producto para poder proponer un sku.');
        }
        $categoryName = '';
        if ($categoryId !== null) {
            $categoryName = ($this->categories->findById($categoryId) ?? throw CategoryNotFound::forId($categoryId))->nameEs();
        }

        $base = SkuSuggestion::base($categoryName, $name);

        return SkuSuggestion::of($categoryName, $name, $this->products->skusStartingWith($base . '-'));
    }
}
