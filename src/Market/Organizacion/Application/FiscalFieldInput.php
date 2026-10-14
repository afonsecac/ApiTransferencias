<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Application;

/**
 * Un campo fiscal tal como llega a un caso de uso (sin depender del transporte HTTP).
 */
final readonly class FiscalFieldInput
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required = false,
        public ?string $pattern = null,
        public ?string $helpText = null,
        public array $options = [],
        public bool $active = true,
        public int $position = 0,
    ) {
    }
}
