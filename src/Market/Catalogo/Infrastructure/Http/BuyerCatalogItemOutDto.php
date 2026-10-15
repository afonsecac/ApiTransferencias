<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Entity\CommunicationPackage;

/**
 * Un elemento de la vista previa del catálogo del comprador (estructura de presentación, no es de entrada): lo que una cuenta ve
 * de un paquete. Los campos propios del mercado (sku, producto, relevancia vigente e imagen de referencia) son opcionales y,
 * sin valor, no aparecen.
 */
class BuyerCatalogItemOutDto
{
    /**
     * @param array{service: string|null, subservice: string|null, area: string|null} $classification
     * @param array<string, string>|null $image las URLs por tamaño de la imagen de referencia
     * @param array{level: int, from?: string, until?: string}|null $relevance
     */
    public function __construct(
        public int $packageId,
        public string $kind,
        public string $name,
        public array $classification,
        public ?float $price,
        public ?string $currency,
        public ?string $sku = null,
        public ?int $productId = null,
        public ?array $relevance = null,
        public ?array $image = null,
    ) {
    }

    public static function from(CommunicationPackage $package): self
    {
        $service = $package->getService();
        $market = $package->getMarket() ?? [];
        $image = null;
        foreach ($market['images'] ?? [] as $candidate) {
            if (($candidate['isReference'] ?? false) === true) {
                $image = $candidate['urls'] ?? null;
            }
        }

        return new self(
            (int) $package->getId(),
            $package->isMarket() ? 'market' : 'communications',
            (string) $package->getName(),
            [
                'service' => $service['name'] ?? null,
                'subservice' => $service['subservice']['name'] ?? null,
                'area' => $service['area']['name'] ?? null,
            ],
            $package->getAmount(),
            $package->getCurrency(),
            $market['sku'] ?? null,
            $package->getMarketProductId(),
            $market['relevance'] ?? null,
            $image,
        );
    }

    /**
     * @return array<string, mixed> sin los campos opcionales que no tienen valor
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);
        foreach (['sku', 'productId', 'relevance', 'image'] as $optional) {
            if ($data[$optional] === null) {
                unset($data[$optional]);
            }
        }

        return $data;
    }
}
