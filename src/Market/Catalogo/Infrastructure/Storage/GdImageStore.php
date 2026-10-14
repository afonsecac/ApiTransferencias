<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Storage;

use App\Market\Catalogo\Domain\ImageStore;
use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\StoredImage;

/**
 * Guarda las imágenes de los productos en un directorio (un volumen que sirve nginx) con GD (COMR-274). De cada una genera
 * tres tamaños por el lado largo (miniatura, listado y detalle), nunca ampliando, y los guarda en WebP con la
 * transparencia intacta: `<dir>/<ab>/<huella>-<tamaño>.webp`, donde `ab` son los dos primeros caracteres de la huella
 * (para no amontonar miles de archivos en una carpeta). Los recursos GD los libera el recolector (imagedestroy() no hace
 * nada desde PHP 8.0 y está deprecada en 8.5). La huella es aleatoria por subida: dos subidas de la misma imagen no
 * comparten archivos y borrar una no toca la otra. No se conserva el original; el tamaño de detalle es el mayor. Solo acepta
 * JPEG, PNG y WebP (se mira el contenido, no la extensión) y rechaza imágenes con demasiados píxeles, que agotarían la memoria
 * al decodificarlas.
 */
final class GdImageStore implements ImageStore
{
    private const DECODERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    private const QUALITY = 82;

    /**
     * @param array<string, int> $sizes nombre del tamaño → píxeles del lado largo
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $sizes = ['thumb' => 150, 'list' => 400, 'detail' => 1200],
        private readonly int $maxPixels = 40_000_000,
    ) {
    }

    public function store(string $sourcePath): StoredImage
    {
        $info = is_file($sourcePath) && filesize($sourcePath) > 0 ? @getimagesize($sourcePath) : false;
        if ($info === false) {
            throw InvalidProductImage::empty();
        }
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        if (!isset(self::DECODERS[$mime])) {
            throw InvalidProductImage::unsupportedMime($mime);
        }
        if ($width < 1 || $height < 1) {
            throw InvalidProductImage::empty();
        }
        if ($width * $height > $this->maxPixels) {
            throw InvalidProductImage::tooManyPixels($this->maxPixels);
        }

        $source = (self::DECODERS[$mime])($sourcePath);
        if ($source === false) {
            throw InvalidProductImage::empty();
        }

        $file = bin2hex(random_bytes(16));
        $written = [];
        try {
            foreach ($this->sizes as $name => $longSide) {
                $path = $this->path($file, $name);
                $this->write($source, $width, $height, $longSide, $path);
                $written[] = $path;
            }
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                @unlink($path);
            }

            throw $e;
        }

        return new StoredImage($file, $mime, (int) filesize($sourcePath), $width, $height);
    }

    public function delete(string $file): void
    {
        // Solo huellas que genera esta clase: nada que pueda salir del directorio.
        if (preg_match('/^[a-f0-9]{32}$/', $file) !== 1) {
            return;
        }
        foreach (array_keys($this->sizes) as $name) {
            @unlink($this->path($file, $name));
        }
    }

    private function path(string $file, string $size): string
    {
        return sprintf('%s/%s/%s-%s.webp', $this->directory, substr($file, 0, 2), $file, $size);
    }

    private function write(\GdImage $source, int $width, int $height, int $longSide, string $path): void
    {
        $scale = min(1.0, $longSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('No se pudo crear el directorio de imágenes "%s".', $directory));
        }
        // Se escribe a un temporal y se renombra: nginx nunca sirve un archivo a medias.
        $temporary = $path . '.tmp';
        $ok = imagewebp($target, $temporary, self::QUALITY);
        if (!$ok || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('No se pudo guardar la imagen.');
        }
    }
}
