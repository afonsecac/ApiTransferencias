<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Estado del producto: borrador, publicado o retirado. Solo los publicados llegan al catálogo del comprador.
 */
enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case WITHDRAWN = 'withdrawn';
}
