<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Domain;

use App\Market\Catalogo\Domain\InvalidGalleryOrder;
use App\Market\Catalogo\Domain\ProductGallery;
use App\Market\Catalogo\Domain\ProductGalleryFull;
use App\Market\Catalogo\Domain\ProductImage;
use App\Market\Catalogo\Domain\ProductImageNotFound;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Catalogo\Domain\ProductGallery
 */
final class ProductGalleryTest extends TestCase
{
    private function image(int $id): ProductImage
    {
        $image = ProductImage::create(9, 'file' . $id, "img-$id.jpg", 'image/jpeg', 1000, 100, 100);
        (new \ReflectionProperty(ProductImage::class, 'id'))->setValue($image, $id);

        return $image;
    }

    /**
     * @param list<int> $ids
     */
    private function gallery(array $ids, int $max = 8): ProductGallery
    {
        $gallery = ProductGallery::of([], $max);
        foreach ($ids as $id) {
            $gallery->add($this->image($id));
        }

        return $gallery;
    }

    /**
     * @return list<int>
     */
    private function order(ProductGallery $gallery): array
    {
        return array_map(static fn (ProductImage $i): int => (int) $i->id(), $gallery->images());
    }

    public function testTheFirstImageIsTheReferenceAndTheNextOnesAreNot(): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $this->assertSame([1, 2, 3], $this->order($gallery));
        $this->assertSame([1, 2, 3], array_map(static fn (ProductImage $i) => $i->position(), $gallery->images()));
        $this->assertSame(1, $gallery->reference()?->id());
        $this->assertSame(1, count(array_filter($gallery->images(), static fn (ProductImage $i) => $i->isReference())));
    }

    public function testAnEmptyGalleryHasNoReference(): void
    {
        $this->assertNull($this->gallery([])->reference());
    }

    public function testItCannotHoldMoreThanTheLimit(): void
    {
        $gallery = $this->gallery([1, 2]);

        $this->expectException(ProductGalleryFull::class);

        ProductGallery::of($gallery->images(), 2)->add($this->image(3));
    }

    public function testItReordersWithAPermutationOfItsImages(): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $gallery->reorder([3, 1, 2]);

        $this->assertSame([3, 1, 2], $this->order($gallery));
        $this->assertSame([1, 2, 3], array_map(static fn (ProductImage $i) => $i->position(), $gallery->images()));
        $this->assertSame(1, $gallery->reference()?->id(), 'Reordenar no cambia cuál es la referencia.');
    }

    /**
     * @dataProvider invalidOrders
     *
     * @param list<int> $ids
     */
    public function testItRejectsAnOrderThatIsNotAPermutation(array $ids): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $this->expectException(InvalidGalleryOrder::class);

        $gallery->reorder($ids);
    }

    /**
     * @return array<string, array{list<int>}>
     */
    public static function invalidOrders(): array
    {
        return ['falta una' => [[1, 2]], 'sobra una' => [[1, 2, 3, 4]], 'repetida' => [[1, 1, 2]], 'ajena' => [[1, 2, 99]]];
    }

    public function testItChangesTheReference(): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $gallery->setReference(2);

        $this->assertSame(2, $gallery->reference()?->id());
        $this->assertSame(1, count(array_filter($gallery->images(), static fn (ProductImage $i) => $i->isReference())));
    }

    public function testItFailsToMakeAnUnknownImageTheReference(): void
    {
        $this->expectException(ProductImageNotFound::class);

        $this->gallery([1, 2])->setReference(99);
    }

    public function testRemovingTheReferencePromotesTheFirstOfTheRest(): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $removed = $gallery->remove(1);

        $this->assertSame(1, $removed->id());
        $this->assertSame([2, 3], $this->order($gallery));
        $this->assertSame([1, 2], array_map(static fn (ProductImage $i) => $i->position(), $gallery->images()), 'Las posiciones se compactan.');
        $this->assertSame(2, $gallery->reference()?->id());
    }

    public function testRemovingAnotherImageKeepsTheReference(): void
    {
        $gallery = $this->gallery([1, 2, 3]);

        $gallery->remove(3);

        $this->assertSame([1, 2], $this->order($gallery));
        $this->assertSame(1, $gallery->reference()?->id());
    }

    public function testRemovingTheLastImageLeavesItEmpty(): void
    {
        $gallery = $this->gallery([1]);

        $gallery->remove(1);

        $this->assertSame([], $gallery->images());
        $this->assertNull($gallery->reference());
    }

    public function testItFailsToRemoveAnUnknownImage(): void
    {
        $this->expectException(ProductImageNotFound::class);

        $this->gallery([1])->remove(99);
    }

    public function testItLoadsAnExistingGalleryInItsStoredOrder(): void
    {
        $a = $this->image(1);
        $a->moveTo(2);
        $b = $this->image(2);
        $b->moveTo(1);
        $b->markAsReference();

        $gallery = ProductGallery::of([$a, $b], 8);

        $this->assertSame([2, 1], $this->order($gallery));
        $this->assertSame(2, $gallery->reference()?->id());
    }
}
