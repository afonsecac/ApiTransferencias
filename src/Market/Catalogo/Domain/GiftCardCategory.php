<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Categoría de una gift card: el subservicio de «Gift Cards», con las mismas que usa DTOne (COMR-276). Qué categorías se
 * ofrecen al inicio sigue por decidir; esta es la lista de partida.
 */
enum GiftCardCategory: string
{
    case RETAIL = 'retail';
    case GAMING = 'gaming';
    case CASH_CARDS = 'cash_cards';
    case FOOD = 'food';
    case ENTERTAINMENT = 'entertainment';
    case TRAVEL_TRANSPORT = 'travel_transport';
}
