<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

/**
 * Texto de búsqueda de un nombre: minúsculas, sin acentos ni signos y con los espacios colapsados. Se calcula en PHP y se
 * guarda en una columna, de modo que buscar no dependa de extensiones de la base de datos.
 */
final class SearchText
{
    public static function normalize(string $text): string
    {
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text;
        $plain = mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', $decomposed));
        $words = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $plain));

        return $words;
    }
}
