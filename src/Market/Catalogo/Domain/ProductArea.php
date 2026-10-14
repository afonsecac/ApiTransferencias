<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Áreas iniciales del mercado (COMR-175); el negocio aporta el resto cuando las tenga.
 */
enum ProductArea: string
{
    case GIFT_CARDS = 'gift_cards';
    case RETAIL = 'retail';
    case SUPERMARKET = 'supermarket';
}
