<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Los datos fiscales de una unidad no cumplen el registro fiscal de su país (COMR-270).
 */
final class InvalidFiscalData extends \DomainException
{
    /**
     * @param array<string, string> $errors código de error (`required`, `type`, `option`, `format`) por clave
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Los datos fiscales no cumplen el registro fiscal del país: ' . implode(', ', array_map(
            static fn (string $key, string $code) => $key . ' (' . $code . ')',
            array_keys($errors),
            $errors,
        )) . '.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
