<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Market\Catalogo\Domain\Relevance;

/**
 * Marca un producto como relevante en las búsquedas, o le quita la marca con nivel 0 (COMR-269).
 */
final class SetProductRelevance
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductDetailsAssembler $assembler,
    ) {
    }

    /**
     * @param string|null $from día de inicio (AAAA-MM-DD); null = sin límite
     * @param string|null $until día de fin, incluido (AAAA-MM-DD); null = sin límite
     *
     * @throws ProductNotFound
     * @throws InvalidProduct nivel fuera de 0..3, fecha mal escrita o fin anterior al inicio
     */
    public function __invoke(int $id, int $level, ?string $from, ?string $until): ProductDetails
    {
        $product = $this->products->findById($id) ?? throw ProductNotFound::forId($id);
        $product->setRelevance(Relevance::of($level, self::day($from), self::day($until)));
        $this->products->save($product);

        return $this->assembler->one($product);
    }

    private static function day(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($day === false || $day->format('Y-m-d') !== $value) {
            throw new InvalidProduct('Las fechas de relevancia se escriben AAAA-MM-DD.');
        }

        return $day;
    }
}
