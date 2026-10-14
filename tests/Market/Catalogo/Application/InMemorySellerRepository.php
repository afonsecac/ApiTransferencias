<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Application;

use App\Market\Catalogo\Domain\Seller;
use App\Market\Catalogo\Domain\SellerRepository;

final class InMemorySellerRepository implements SellerRepository
{
    /** @var array<int, Seller> */
    public array $sellers = [];

    public int $saves = 0;

    private int $nextId = 1;

    public function findById(int $id): ?Seller
    {
        return $this->sellers[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Seller
    {
        foreach ($this->sellers as $seller) {
            if ($seller->slug()->value() === $slug) {
                return $seller;
            }
        }

        return null;
    }

    public function findByOrgUnit(int $orgUnitId): ?Seller
    {
        foreach ($this->sellers as $seller) {
            if ($seller->orgUnitId() === $orgUnitId) {
                return $seller;
            }
        }

        return null;
    }

    public function findPlatform(): ?Seller
    {
        foreach ($this->sellers as $seller) {
            if ($seller->isPlatform()) {
                return $seller;
            }
        }

        return null;
    }

    public function search(?string $query, ?bool $active): array
    {
        $found = array_filter($this->sellers, static function (Seller $s) use ($query, $active): bool {
            if ($active !== null && $s->isActive() !== $active) {
                return false;
            }

            return $query === null || $query === ''
                || stripos($s->name(), $query) !== false
                || stripos($s->slug()->value(), $query) !== false;
        });
        usort($found, static fn (Seller $a, Seller $b) => strcmp($a->name(), $b->name()));

        return $found;
    }

    public function save(Seller $seller): void
    {
        if ($seller->id() === null) {
            (new \ReflectionProperty(Seller::class, 'id'))->setValue($seller, $this->nextId++);
        }
        $this->sellers[$seller->id()] = $seller;
        ++$this->saves;
    }
}
