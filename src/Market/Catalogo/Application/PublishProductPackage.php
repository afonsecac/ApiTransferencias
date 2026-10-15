<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Amount;
use App\Market\Catalogo\Domain\InvalidProduct;
use App\Market\Catalogo\Domain\PackageOffer;
use App\Market\Catalogo\Domain\ProductNotFound;
use App\Market\Catalogo\Domain\ProductPackages;
use App\Market\Catalogo\Domain\ProductRepository;

/**
 * Ofrece un producto publicado al comprador como paquete, con un precio mínimo (COMR-275). El precio es provisional: se
 * sustituirá por las tarifas del hito 3.
 */
final class PublishProductPackage
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductPackages $packages,
    ) {
    }

    /**
     * @param string $price importe positivo con hasta dos decimales y punto decimal
     *
     * @throws ProductNotFound
     * @throws InvalidProduct producto sin publicar, importe o moneda inválidos
     */
    public function __invoke(int $productId, string $price, string $currency): PackageOffer
    {
        $product = $this->products->findById($productId) ?? throw ProductNotFound::forId($productId);
        $amount = Amount::fromString($price);
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidProduct(sprintf('La moneda "%s" no es válida: tres letras (p. ej. USD).', $currency));
        }
        if (!$product->isPublished()) {
            throw new InvalidProduct('Solo se ofrece al comprador un producto publicado.');
        }

        return $this->packages->publish($product, $amount, $currency);
    }
}
