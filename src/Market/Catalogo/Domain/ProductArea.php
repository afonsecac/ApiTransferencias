<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Área de un producto: una subdivisión que cuelga de un subservicio (T4). «Supermercado» es un área del subservicio
 * «Retail»; «Retail» y «Gift Cards» no son áreas, son subservicio y servicio. El negocio aporta más áreas cuando las tenga.
 */
enum ProductArea: string
{
    case SUPERMARKET = 'supermarket';

    /** El subservicio al que pertenece el área. */
    public function subservice(): GiftCardCategory
    {
        return match ($this) {
            self::SUPERMARKET => GiftCardCategory::RETAIL,
        };
    }
}
