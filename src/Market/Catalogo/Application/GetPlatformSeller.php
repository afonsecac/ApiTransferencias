<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerNotFound;
use App\Market\Catalogo\Domain\SellerRepository;

/**
 * El único vendedor de plataforma: el que usan por defecto los productos propios.
 */
final class GetPlatformSeller
{
    public function __construct(private readonly SellerRepository $sellers)
    {
    }

    /**
     * @throws SellerNotFound
     */
    public function __invoke(): Seller
    {
        return $this->sellers->findPlatform() ?? throw SellerNotFound::platform();
    }
}
