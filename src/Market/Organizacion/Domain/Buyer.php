<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Los datos de un comprador (una cuenta de la API, `Client`) que importan a la unidad organizativa. Es una
 * vista de solo lectura: el `Client` sigue siendo de comunicaciones y este contexto no lo conoce más allá.
 */
final readonly class Buyer
{
    /**
     * @param string $country ISO-3166 alfa-3, como lo guarda el Client
     */
    public function __construct(
        public int $clientId,
        public string $name,
        public string $country,
        public string $identification,
        public ?string $address,
        public string $email,
    ) {
    }
}
