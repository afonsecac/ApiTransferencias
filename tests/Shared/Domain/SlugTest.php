<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Slug;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Shared\Domain\Slug
 */
final class SlugTest extends TestCase
{
    public function testItIsDerivedFromTextInLowercaseWithoutAccents(): void
    {
        $this->assertSame('cafe-con-leche', Slug::fromText('  Café   con Leche! ')->value());
        $this->assertSame('arroz-grano-largo-1-kg', Slug::fromText('Arroz GRANO largo 1 kg')->value());
    }

    public function testFreeReturnsTheBaseSlugWhenNothingTakesIt(): void
    {
        $this->assertSame('casa-yeya', Slug::free('Casa Yeya', static fn (string $slug): bool => false)->value());
    }

    public function testFreeAddsTheNextNumberWhileTheSlugIsTaken(): void
    {
        $taken = ['casa-yeya', 'casa-yeya-2', 'casa-yeya-3'];

        $slug = Slug::free('Casa Yeya', static fn (string $slug): bool => in_array($slug, $taken, true));

        $this->assertSame('casa-yeya-4', $slug->value());
    }

    public function testFreeKeepsTheSuffixWithinTheMaximumLength(): void
    {
        $long = str_repeat('a', 70);
        $base = str_repeat('a', Slug::MAX_LENGTH);

        $slug = Slug::free($long, static fn (string $slug): bool => $slug === $base);

        $this->assertSame(Slug::MAX_LENGTH, strlen($slug->value()));
        $this->assertStringEndsWith('-2', $slug->value());
    }

    public function testFreeGivesUpAfterManyCollisions(): void
    {
        $this->expectException(\App\Shared\Domain\InvalidSlug::class);

        Slug::free('Casa Yeya', static fn (string $slug): bool => true);
    }
}
