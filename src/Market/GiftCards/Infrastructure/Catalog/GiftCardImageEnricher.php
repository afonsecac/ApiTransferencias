<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Catalog;

use App\Entity\CommunicationPackage;
use App\Market\GiftCards\Application\GiftCardDesign;
use App\Market\GiftCards\Domain\GiftCardProducts;
use App\Service\Catalog\MarketPackageEnricher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Añade a los paquetes de gift cards (productos digitales con valor) la imagen de su diseño en `market.images`, después de las
 * que se hayan subido: el mismo diseño que la tarjeta digital del dashboard, con el nombre del producto y el precio del paquete,
 * sin número ni PIN.
 *
 * El SVG se guarda como un archivo más del volumen que nginx sirve en /market-images/, con la huella del contenido en el nombre
 * (como las imágenes subidas): la URL es pública e inmutable, y si cambia el precio cambia la URL, así que la caché larga nunca
 * sirve un precio viejo. Escribir el archivo la primera vez es idempotente: el mismo contenido da siempre el mismo nombre.
 */
#[AsDecorator(decorates: MarketPackageEnricher::class)]
final class GiftCardImageEnricher implements MarketPackageEnricher
{
    public function __construct(
        #[AutowireDecorated]
        private readonly MarketPackageEnricher $inner,
        private readonly GiftCardProducts $products,
        private readonly GiftCardDesign $design,
        #[Autowire('%market.images.dir%')]
        private readonly string $directory,
        #[Autowire('%market.images.url_prefix%')]
        private readonly string $prefix = '/market-images',
        #[Autowire('%env(string:default::MARKET_IMAGES_BASE_URL)%')]
        private readonly string $baseUrl = '',
    ) {
    }

    public function enrich(iterable $packages, \DateTimeImmutable $now): void
    {
        $packages = is_array($packages) ? $packages : iterator_to_array($packages, false);
        $this->inner->enrich($packages, $now);

        $candidates = array_filter($packages, static fn (CommunicationPackage $p): bool => $p->getMarketProductId() !== null && $p->getMarket() !== null && $p->getAmount() !== null);
        if ($candidates === []) {
            return;
        }

        $giftCards = $this->products->findMany(array_values(array_unique(array_map(static fn (CommunicationPackage $p): int => (int) $p->getMarketProductId(), $candidates))));

        foreach ($candidates as $package) {
            $product = $giftCards[(int) $package->getMarketProductId()] ?? null;
            if ($product === null || !$product->digital) {
                continue;
            }

            $label = sprintf('%s %s', number_format((float) $package->getAmount(), 2, '.', ''), (string) $package->getCurrency());
            $url = $this->store($this->design->svg($product->name, trim($label)));

            $market = $package->getMarket() ?? [];
            $market['images'] = [...($market['images'] ?? []), [
                'urls' => ['thumb' => $url, 'list' => $url, 'detail' => $url],
                'kind' => 'tarjeta',
                'alt' => ['es' => 'Tarjeta de regalo ' . $product->name, 'en' => 'Gift card ' . $product->name],
                'isGenerated' => true,
            ]];
            $package->setMarket($market);
        }
    }

    /** Guarda el SVG con su huella (si no estaba) y devuelve su URL pública. */
    private function store(string $svg): string
    {
        $hash = sha1($svg);
        $folder = substr($hash, 0, 2);
        $path = sprintf('%s/cards/%s/%s.svg', rtrim($this->directory, '/'), $folder, $hash);

        if (!is_file($path)) {
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('No se pudo crear "%s".', $dir));
            }
            $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
            file_put_contents($tmp, $svg);
            rename($tmp, $path);
        }

        return sprintf('%s%s/cards/%s/%s.svg', rtrim($this->baseUrl, '/'), $this->prefix, $folder, $hash);
    }
}
