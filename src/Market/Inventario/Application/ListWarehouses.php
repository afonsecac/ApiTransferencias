<?php

declare(strict_types=1);

namespace App\Market\Inventario\Application;

use App\Market\Inventario\Domain\WarehouseMunicipalities;
use App\Market\Inventario\Domain\WarehouseOwners;
use App\Market\Inventario\Domain\WarehouseRepository;

final class ListWarehouses
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly WarehouseOwners $owners,
        private readonly WarehouseMunicipalities $municipalities,
    ) {
    }

    /**
     * @param string|null $query busca en el nombre, el identificador y el municipio, sin distinguir acentos ni mayúsculas
     * @param string|null $regime `platform` o `partner`
     * @param string|null $status `draft`, `active` o `inactive`
     *
     * @return list<WarehouseDetails> por nombre
     */
    public function __invoke(?string $query, ?string $regime, ?string $status): array
    {
        $needle = $query === null ? '' : self::fold($query);
        $details = array_map(
            fn ($w) => WarehouseDetails::of($w, $this->owners, $this->municipalities),
            $this->warehouses->findAll(),
        );
        $details = array_filter($details, static function (WarehouseDetails $d) use ($needle, $regime, $status): bool {
            return ($regime === null || $d->regime === $regime)
                && ($status === null || $d->warehouse->status()->value === $status)
                && ($needle === '' || str_contains(self::fold($d->warehouse->name() . ' ' . $d->warehouse->slug()->value() . ' ' . ($d->municipality->name ?? '')), $needle));
        });
        usort($details, static fn (WarehouseDetails $a, WarehouseDetails $b) => [self::fold($a->warehouse->name()), $a->warehouse->id()] <=> [self::fold($b->warehouse->name()), $b->warehouse->id()]);

        return $details;
    }

    private static function fold(string $text): string
    {
        $plain = mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text));

        return trim((string) preg_replace('/\s+/', ' ', $plain));
    }
}
