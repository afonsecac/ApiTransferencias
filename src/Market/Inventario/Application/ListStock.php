<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\StockItemType;
use App\Market\Inventario\Domain\StockLedger;
use App\Market\Inventario\Domain\StockLine;
use App\Market\Inventario\Domain\StockProducts;
use App\Market\Inventario\Domain\WarehouseRepository;

final class ListStock
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WarehouseRepository $warehouses,
        private readonly StockProducts $products,
    ) {
    }

    /**
     * Las existencias por almacén y artículo con en mano, reservado, seguridad y disponible.
     *
     * @param string|null $query busca en el nombre y el sku del producto, sin distinguir acentos ni mayúsculas
     * @param string|null $type `product` o `combo`
     *
     * @return list<StockLineDetails> por producto y almacén
     */
    public function __invoke(?int $warehouseId, ?string $query, ?string $type, bool $lowOnly): array
    {
        $lines = $this->ledger->lines($warehouseId, $type === null ? null : StockItemType::tryFrom($type));
        $products = $this->products->findMany(array_values(array_unique(array_map(static fn (StockLine $l) => $l->itemId(), $lines))));
        $warehouses = [];
        foreach ($this->warehouses->findAll() as $warehouse) {
            $warehouses[(int) $warehouse->id()] = $warehouse;
        }

        $needle = $query === null ? '' : self::fold($query);
        $details = [];
        foreach ($lines as $line) {
            $detail = new StockLineDetails($line, $warehouses[$line->warehouseId()] ?? null, $products[$line->itemId()] ?? null);
            if ($lowOnly && !$detail->low) {
                continue;
            }
            if ($needle !== '' && !str_contains(self::fold(($detail->product->name ?? '') . ' ' . ($detail->product->sku ?? '')), $needle)) {
                continue;
            }
            $details[] = $detail;
        }
        usort($details, static fn (StockLineDetails $a, StockLineDetails $b) => [self::fold($a->product->name ?? ''), $a->warehouse?->name(), $a->line->id()] <=> [self::fold($b->product->name ?? ''), $b->warehouse?->name(), $b->line->id()]);

        return $details;
    }

    private static function fold(string $text): string
    {
        $plain = mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text));

        return trim((string) preg_replace('/\s+/', ' ', $plain));
    }
}
