<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\ProductImage;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Domain\ProductImage
 */
final class ProductImageTest extends TestCase
{
    private function image(string $mime = 'image/jpeg', int $bytes = 120_000, int $width = 1200, int $height = 800): ProductImage
    {
        return ProductImage::create(9, 'a1b2c3', 'arroz-frente.jpg', $mime, $bytes, $width, $height);
    }

    public function testItKeepsWhatWasUploaded(): void
    {
        $image = $this->image();

        $this->assertSame(9, $image->productId());
        $this->assertSame('a1b2c3', $image->file());
        $this->assertSame('arroz-frente.jpg', $image->originalName());
        $this->assertSame('image/jpeg', $image->mime());
        $this->assertSame(120_000, $image->bytes());
        $this->assertSame([1200, 800], [$image->width(), $image->height()]);
        $this->assertNull($image->kind());
        $this->assertNull($image->altEs());
        $this->assertNull($image->altEn());
        $this->assertFalse($image->isReference());
        $this->assertSame(0, $image->position());
    }

    /**
     * @dataProvider allowedMimes
     */
    public function testItAcceptsJpegPngAndWebp(string $mime): void
    {
        $this->assertSame($mime, $this->image($mime)->mime());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedMimes(): array
    {
        return ['jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];
    }

    /**
     * @dataProvider invalidData
     */
    public function testItRejectsWhatIsNotAnImage(string $mime, int $bytes, int $width, int $height): void
    {
        $this->expectException(InvalidProductImage::class);

        $this->image($mime, $bytes, $width, $height);
    }

    /**
     * @return array<string, array{string, int, int, int}>
     */
    public static function invalidData(): array
    {
        return [
            'gif' => ['image/gif', 1000, 10, 10],
            'svg' => ['image/svg+xml', 1000, 10, 10],
            'sin bytes' => ['image/png', 0, 10, 10],
            'sin ancho' => ['image/png', 1000, 0, 10],
            'sin alto' => ['image/png', 1000, 10, 0],
        ];
    }

    public function testItIsDescribedWithATypeAndAnAltTextInBothLanguages(): void
    {
        $image = $this->image();

        $image->describe('frente', '  Arroz, frente del paquete ', 'Rice, front of the pack');

        $this->assertSame('frente', $image->kind());
        $this->assertSame('Arroz, frente del paquete', $image->altEs());
        $this->assertSame('Rice, front of the pack', $image->altEn());
    }

    public function testBlankDescriptionsAreCleared(): void
    {
        $image = $this->image();
        $image->describe('frente', 'algo', 'something');

        $image->describe('  ', '', null);

        $this->assertNull($image->kind());
        $this->assertNull($image->altEs());
        $this->assertNull($image->altEn());
    }

    public function testTheTypeAndTheAltTextHaveALimit(): void
    {
        $this->expectException(InvalidProductImage::class);

        $this->image()->describe(str_repeat('x', 41), null, null);
    }

    public function testTheAltTextHasALimit(): void
    {
        $this->expectException(InvalidProductImage::class);

        $this->image()->describe(null, str_repeat('x', 201), null);
    }

    public function testItIsMovedAndMarkedAsTheReference(): void
    {
        $image = $this->image();

        $image->moveTo(3);
        $image->markAsReference();

        $this->assertSame(3, $image->position());
        $this->assertTrue($image->isReference());

        $image->unmarkAsReference();
        $this->assertFalse($image->isReference());
    }

    public function testThePositionIsPositive(): void
    {
        $this->expectException(InvalidProductImage::class);

        $this->image()->moveTo(0);
    }
}
