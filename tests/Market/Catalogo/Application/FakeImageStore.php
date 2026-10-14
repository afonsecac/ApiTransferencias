<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\ImageStore;
use App\Market\Catalogo\Domain\InvalidProductImage;
use App\Market\Catalogo\Domain\StoredImage;

final class FakeImageStore implements ImageStore
{
    /** @var list<string> */
    public array $stored = [];

    /** @var list<string> */
    public array $deleted = [];

    public bool $rejects = false;

    public function store(string $sourcePath): StoredImage
    {
        if ($this->rejects) {
            throw InvalidProductImage::unsupportedMime('text/plain');
        }
        $file = 'huella' . (count($this->stored) + 1);
        $this->stored[] = $file;

        return new StoredImage($file, 'image/jpeg', 90_000, 1200, 800);
    }

    public function delete(string $file): void
    {
        $this->deleted[] = $file;
    }
}
