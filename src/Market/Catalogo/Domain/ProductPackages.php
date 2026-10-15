<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Puerto hacia el catálogo del comprador (COMR-275): un producto suelto también es un paquete. Quién lo implementa es la
 * infraestructura, que conoce cómo se guarda un paquete (clasificación, contrato de precio y proveedor propio).
 */
interface ProductPackages
{
    /**
     * Ofrece el producto como paquete con ese precio; si ya tenía paquete, lo conserva, cambia el precio y lo reactiva.
     *
     * @param string $currency tres letras, ya en mayúsculas
     */
    public function publish(Product $product, Amount $price, string $currency): PackageOffer;

    /** Deja de ofrecer el paquete del producto, sin borrarlo. false si el producto nunca tuvo paquete. */
    public function retire(int $productId): bool;

    public function findFor(int $productId): ?PackageOffer;
}
