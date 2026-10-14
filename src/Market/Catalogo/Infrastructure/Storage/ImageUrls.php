<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Storage;

/**
 * Las URLs públicas de los tamaños de una imagen. La API devuelve siempre estas URLs, nunca la imagen incrustada, para poder
 * mover el almacenamiento (hoy un volumen servido por nginx) sin que los clientes lo noten. Con `baseUrl` vacío son rutas
 * relativas al host de la API.
 */
final class ImageUrls
{
    /**
     * @param list<string> $sizes los nombres de los tamaños que genera GdImageStore
     */
    public function __construct(
        private readonly string $prefix = '/market-images',
        private readonly string $baseUrl = '',
        private readonly array $sizes = ['thumb', 'list', 'detail'],
    ) {
    }

    /**
     * @return array<string, string> tamaño → URL
     */
    public function for(string $file): array
    {
        $urls = [];
        foreach ($this->sizes as $size) {
            $urls[$size] = sprintf('%s%s/%s/%s-%s.webp', rtrim($this->baseUrl, '/'), $this->prefix, substr($file, 0, 2), $file, $size);
        }

        return $urls;
    }
}
