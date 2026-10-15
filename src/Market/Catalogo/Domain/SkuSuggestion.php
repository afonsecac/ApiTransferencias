<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * El sku que se propone al crear un producto (COMR-277): CATEGORÍA-NOMBRE-NNN en mayúsculas y sin acentos. CATEGORÍA son las tres
 * primeras letras de la categoría (o GEN si no hay), NOMBRE la primera palabra con sentido del nombre (sin artículos ni
 * conectores, máximo doce caracteres) y NNN un consecutivo que sigue al más alto ya usado con el mismo prefijo, de modo que
 * la propuesta nunca repite un sku existente. Es solo una sugerencia: la persona puede escribir otro.
 */
final class SkuSuggestion
{
    private const PREFIX_LENGTH = 3;
    private const KEYWORD_LENGTH = 12;
    private const GENERIC_PREFIX = 'GEN';
    private const STOP_WORDS = ['DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS', 'EN', 'CON', 'Y', 'E', 'PARA', 'POR', 'UN', 'UNA', 'UNOS', 'UNAS', 'AL', 'A', 'O'];

    private function __construct()
    {
    }

    /**
     * @param list<string> $existingSkus los sku ya usados (basta con los que empiezan por el prefijo)
     *
     * @throws InvalidProduct si el nombre no da ninguna palabra con la que armar el sku
     */
    public static function of(string $categoryName, string $productName, array $existingSkus): Sku
    {
        $keyword = self::keyword($productName);
        $base = self::prefix($categoryName) . '-' . $keyword;

        $highest = 0;
        foreach ($existingSkus as $sku) {
            if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)$/', strtoupper(trim($sku)), $match) === 1) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        return new Sku(sprintf('%s-%03d', $base, $highest + 1));
    }

    /** El prefijo con el que se buscan los sku existentes de una propuesta, sin el consecutivo. */
    public static function base(string $categoryName, string $productName): string
    {
        return self::prefix($categoryName) . '-' . self::keyword($productName);
    }

    private static function prefix(string $categoryName): string
    {
        $words = self::words($categoryName);

        return $words === [] ? self::GENERIC_PREFIX : substr($words[0], 0, self::PREFIX_LENGTH);
    }

    private static function keyword(string $productName): string
    {
        foreach (self::words($productName) as $word) {
            if (!in_array($word, self::STOP_WORDS, true)) {
                return substr($word, 0, self::KEYWORD_LENGTH);
            }
        }

        throw new InvalidProduct('Escribe el nombre del producto para poder proponer un sku.');
    }

    /**
     * @return list<string> palabras en mayúsculas, sin acentos y solo con letras y números
     */
    private static function words(string $text): array
    {
        $ascii = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text) ?? $text;
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $ascii));

        return array_values(array_filter(explode(' ', trim($clean)), static fn (string $word): bool => $word !== ''));
    }
}
