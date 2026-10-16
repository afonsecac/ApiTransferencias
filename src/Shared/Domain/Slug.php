<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Identificador legible y estable de un vendedor: minúsculas ASCII, dígitos y guiones sueltos (máx. 60).
 */
final readonly class Slug
{
    public const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    public const MAX_LENGTH = 60;

    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidSlug(sprintf('El identificador "%s" no es válido: minúsculas, números y guiones sueltos, máximo %d.', $value, self::MAX_LENGTH));
        }
        $this->value = $value;
    }

    public static function fromText(string $text): self
    {
        $ascii = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text) ?? $text;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

        return new self(substr($slug, 0, self::MAX_LENGTH) === '' ? '' : rtrim(substr($slug, 0, self::MAX_LENGTH), '-'));
    }

    /**
     * El identificador derivado de un texto que nada ocupa todavía: el del texto tal cual y, si ya está en uso, con -2, -3…
     * (el sufijo siempre cabe en el máximo). Solo para los que se proponen solos; uno que escribe una persona nunca se cambia.
     *
     * @param callable(string): bool $taken
     *
     * @throws InvalidSlug si el texto no da un identificador válido o hay demasiadas colisiones
     */
    public static function free(string $text, callable $taken): self
    {
        $base = self::fromText($text);
        if (!$taken($base->value())) {
            return $base;
        }
        for ($number = 2; $number <= 99; ++$number) {
            $suffix = '-' . $number;
            $candidate = rtrim(substr($base->value(), 0, self::MAX_LENGTH - strlen($suffix)), '-') . $suffix;
            if (!$taken($candidate)) {
                return new self($candidate);
            }
        }

        throw new InvalidSlug(sprintf('No se encontró un identificador libre para "%s": escribe uno a mano.', $text));
    }

    public function value(): string
    {
        return $this->value;
    }
}
