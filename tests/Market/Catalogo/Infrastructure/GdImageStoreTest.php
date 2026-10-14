<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Infrastructure\Storage\GdImageStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Infrastructure\Storage\GdImageStore
 */
final class GdImageStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/market-images-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $shard) {
            @rmdir($shard);
        }
        @rmdir($this->dir);
    }

    private function store(int $maxPixels = 40_000_000): GdImageStore
    {
        return new GdImageStore($this->dir, ['thumb' => 150, 'list' => 400, 'detail' => 1200], $maxPixels);
    }

    /**
     * @param 'jpeg'|'png'|'webp'|'gif' $format
     */
    private function sample(int $width, int $height, string $format = 'jpeg', bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 255, 0, 0, 127));
        } else {
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));
        }
        $path = sys_get_temp_dir() . '/sample-' . bin2hex(random_bytes(4)) . '.' . $format;
        match ($format) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
            'gif' => imagegif($image, $path),
        };

        return $path;
    }

    /**
     * @return array{int, int}
     */
    private function size(string $file, string $variant): array
    {
        $info = getimagesize($this->dir . '/' . substr($file, 0, 2) . '/' . $file . '-' . $variant . '.webp');
        $this->assertIsArray($info);

        return [$info[0], $info[1]];
    }

    public function testItGeneratesThreeSizesByTheLongSideKeepingTheAspectRatio(): void
    {
        $source = $this->sample(2000, 1000);

        $stored = $this->store()->store($source);

        $this->assertSame([150, 75], $this->size($stored->file, 'thumb'));
        $this->assertSame([400, 200], $this->size($stored->file, 'list'));
        $this->assertSame([1200, 600], $this->size($stored->file, 'detail'));
        $this->assertSame('image/jpeg', $stored->mime);
        $this->assertSame([2000, 1000], [$stored->width, $stored->height]);
        $this->assertSame(filesize($source), $stored->bytes);
        unlink($source);
    }

    public function testATallImageIsScaledByItsHeight(): void
    {
        $source = $this->sample(500, 1500);

        $stored = $this->store()->store($source);

        $this->assertSame([50, 150], $this->size($stored->file, 'thumb'));
        $this->assertSame([400, 1200], $this->size($stored->file, 'detail'));
        unlink($source);
    }

    public function testItNeverEnlargesASmallImage(): void
    {
        $source = $this->sample(100, 50);

        $stored = $this->store()->store($source);

        foreach (['thumb', 'list', 'detail'] as $variant) {
            $this->assertSame([100, 50], $this->size($stored->file, $variant), $variant);
        }
        unlink($source);
    }

    public function testItAcceptsPngAndWebpAndKeepsTransparency(): void
    {
        $png = $this->sample(300, 300, 'png', true);
        $webp = $this->sample(300, 300, 'webp');

        $storedPng = $this->store()->store($png);
        $storedWebp = $this->store()->store($webp);

        $this->assertSame('image/png', $storedPng->mime);
        $this->assertSame('image/webp', $storedWebp->mime);
        $variant = imagecreatefromwebp($this->dir . '/' . substr($storedPng->file, 0, 2) . '/' . $storedPng->file . '-list.webp');
        $this->assertNotFalse($variant);
        $this->assertSame(127, (imagecolorat($variant, 5, 5) >> 24) & 0x7F, 'El fondo transparente se conserva.');
        unlink($png);
        unlink($webp);
    }

    public function testEachUploadGetsItsOwnFilesEvenForTheSameImage(): void
    {
        $source = $this->sample(300, 300);

        $first = $this->store()->store($source);
        $second = $this->store()->store($source);

        $this->assertNotSame($first->file, $second->file, 'Borrar una imagen no puede quitarle los archivos a otra.');
        unlink($source);
    }

    /**
     * @dataProvider notAllowed
     */
    public function testItRejectsWhatIsNotAJpegPngOrWebpImage(string $content): void
    {
        $path = sys_get_temp_dir() . '/falso-' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($path, $content);

        try {
            $this->store()->store($path);
            $this->fail('Debió rechazar el archivo');
        } catch (InvalidProductImage) {
            $this->assertSame([], glob($this->dir . '/*/*') ?: [], 'No deja archivos a medias.');
        } finally {
            unlink($path);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notAllowed(): array
    {
        return [
            'texto' => ['esto no es una imagen'],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'],
            'php' => ['<?php echo 1;'],
            'vacío' => [''],
        ];
    }

    public function testItRejectsAGif(): void
    {
        $gif = $this->sample(10, 10, 'gif');

        $this->expectException(InvalidProductImage::class);

        try {
            $this->store()->store($gif);
        } finally {
            unlink($gif);
        }
    }

    public function testItRejectsAnImageWithTooManyPixels(): void
    {
        $source = $this->sample(400, 400);

        $this->expectException(InvalidProductImage::class);

        try {
            $this->store(maxPixels: 100_000)->store($source);
        } finally {
            unlink($source);
        }
    }

    public function testDeleteRemovesAllTheSizesAndIsIdempotent(): void
    {
        $source = $this->sample(800, 800);
        $stored = $this->store()->store($source);
        $store = $this->store();

        $store->delete($stored->file);
        $store->delete($stored->file);

        $this->assertSame([], glob($this->dir . '/*/*') ?: []);
        unlink($source);
    }

    public function testDeleteIgnoresAFileNameThatCouldEscapeTheDirectory(): void
    {
        $outside = sys_get_temp_dir() . '/outside-' . bin2hex(random_bytes(4)) . '-thumb.webp';
        file_put_contents($outside, 'x');

        $this->store()->delete('../' . basename($outside, '-thumb.webp'));

        $this->assertFileExists($outside);
        unlink($outside);
    }
}
