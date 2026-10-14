<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

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
            throw new InvalidSeller(sprintf('El identificador "%s" no es válido: minúsculas, números y guiones sueltos, máximo %d.', $value, self::MAX_LENGTH));
        }
        $this->value = $value;
    }

    public static function fromText(string $text): self
    {
        $ascii = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text) ?? $text;
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

        return new self(substr($slug, 0, self::MAX_LENGTH) === '' ? '' : rtrim(substr($slug, 0, self::MAX_LENGTH), '-'));
    }

    public function value(): string
    {
        return $this->value;
    }
}
