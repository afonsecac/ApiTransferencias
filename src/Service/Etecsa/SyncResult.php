<?php

namespace App\Service\Etecsa;

final class SyncResult
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
    ) {
    }

    public function add(SyncResult $other): self
    {
        return new self(
            $this->created + $other->created,
            $this->updated + $other->updated,
            $this->skipped + $other->skipped,
        );
    }

    public function __toString(): string
    {
        return "created={$this->created}, updated={$this->updated}, skipped={$this->skipped}";
    }
}
