<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Market\Catalogo\Domain\ImageSettings;
use App\Repository\SysConfigRepository;

/**
 * Los límites de las imágenes salen de la configuración del sistema (pantalla de configuración): se pueden cambiar sin
 * desplegar. Si falta o no es un entero positivo, vale el valor por defecto (4 MB y 8 imágenes).
 */
final class SysConfigImageSettings implements ImageSettings
{
    public const MAX_BYTES_KEY = 'market.images.max_bytes';
    public const MAX_PER_PRODUCT_KEY = 'market.images.max_per_product';
    public const DEFAULT_MAX_BYTES = 4_000_000;
    public const DEFAULT_MAX_PER_PRODUCT = 8;

    public function __construct(private readonly SysConfigRepository $config)
    {
    }

    public function maxBytes(): int
    {
        return $this->positive(self::MAX_BYTES_KEY, self::DEFAULT_MAX_BYTES);
    }

    public function maxPerProduct(): int
    {
        return $this->positive(self::MAX_PER_PRODUCT_KEY, self::DEFAULT_MAX_PER_PRODUCT);
    }

    private function positive(string $key, int $default): int
    {
        $value = $this->config->findCachedValue($key, true);

        return $value !== null && ctype_digit(trim($value)) && (int) trim($value) > 0 ? (int) trim($value) : $default;
    }
}
