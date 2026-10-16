<?php

declare(strict_types=1);

namespace App\Market\Distribucion\Domain;

/** Algo que no es un error pero hay que confirmar a propósito: una tarifa 0 o un máximo vacío (sin tope). */
final class CoverageConfirmationRequired extends \DomainException
{
    public const ZERO_RATE = 'zero_rate';
    public const NO_MAX = 'no_max';

    private function __construct(private readonly string $what, string $message)
    {
        parent::__construct($message);
    }

    public static function zeroRate(): self
    {
        return new self(self::ZERO_RATE, 'Una tarifa de 0 hace la entrega gratis por ese concepto: confírmalo para guardarla.');
    }

    public static function noMax(): self
    {
        return new self(self::NO_MAX, 'Sin precio máximo la entrega no tiene tope: confírmalo para guardarla.');
    }

    public function what(): string
    {
        return $this->what;
    }
}
