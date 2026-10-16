<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Persistence;

use App\Market\Catalogo\Application\GetProductGiftCardFacts;
use App\Market\Catalogo\Application\ProductGiftCardFacts;
use App\Market\GiftCards\Domain\GiftCardCostCenter;
use App\Market\GiftCards\Domain\GiftCardProduct;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Market\Organizacion\Application\GetOrgUnit;
use App\Market\Organizacion\Domain\CostCenter;
use App\Market\Organizacion\Domain\OrgUnitNotFound;

/**
 * Puerto síncrono explícito de las gift cards hacia Catálogo (el valor del producto) y Organización (los centros de costo de su
 * vendedor) a través de sus casos de uso (ADR-001): solo lectura.
 */
final class CatalogoOrganizacionGiftCardProducts implements GiftCardProducts
{
    public function __construct(
        private readonly GetProductGiftCardFacts $facts,
        private readonly GetOrgUnit $getOrgUnit,
    ) {
    }

    public function find(int $id): ?GiftCardProduct
    {
        $facts = ($this->facts)($id);

        return $facts === null ? null : $this->translate($facts);
    }

    public function findMany(array $ids): array
    {
        return array_map($this->translate(...), $this->facts->many($ids));
    }

    private function translate(ProductGiftCardFacts $f): GiftCardProduct
    {
        $centers = [];
        try {
            foreach (($this->getOrgUnit)($f->sellerOrgUnitId)->costCenters as $c) {
                /** @var CostCenter $c */
                $centers[] = new GiftCardCostCenter((int) $c->id(), $c->code(), $c->name(), $c->isPrincipal(), $c->isActive());
            }
        } catch (OrgUnitNotFound) {
            // Sin unidad no hay centros: la emisión se rechaza con su mensaje.
        }

        return new GiftCardProduct($f->id, $f->name, $f->digital, $f->fixed, $f->currency, $f->minMinor, $f->maxMinor, $centers);
    }
}
