<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Los atributos libres de un producto (color, sabor, talla…, COMR-284): una lista ordenada de pares nombre-valor, cada uno con su
 * texto por idioma. El español es obligatorio y es el idioma de respaldo; el inglés es opcional (una traducción en blanco se
 * descarta). No se repite un nombre en el mismo producto (sin distinguir mayúsculas ni acentos) y hay un tope de atributos.
 */
final readonly class ProductAttributes
{
    public const MAX_ATTRIBUTES = 20;
    public const NAME_MAX_LENGTH = 60;
    public const VALUE_MAX_LENGTH = 120;

    /**
     * @param list<array{name: array<string, string>, value: array<string, string>}> $items
     */
    private function __construct(private array $items)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<mixed> $items cada elemento, `['name' => ['es' => …, 'en' => …], 'value' => ['es' => …, 'en' => …]]`
     *
     * @throws InvalidProduct
     */
    public static function fromArray(array $items): self
    {
        if (count($items) > self::MAX_ATTRIBUTES) {
            throw new InvalidProduct(sprintf('Un producto admite como mucho %d atributos.', self::MAX_ATTRIBUTES));
        }

        $clean = [];
        $seen = [];
        foreach (array_values($items) as $position => $item) {
            $number = $position + 1;
            if (!is_array($item)) {
                throw new InvalidProduct(sprintf('El atributo %d no es válido: lleva un nombre y un valor.', $number));
            }
            $name = self::texts($item['name'] ?? null, 'nombre', self::NAME_MAX_LENGTH, $number);
            $value = self::texts($item['value'] ?? null, 'valor', self::VALUE_MAX_LENGTH, $number);

            $key = self::fold($name[ProductTexts::FALLBACK]);
            if (isset($seen[$key])) {
                throw new InvalidProduct(sprintf('El atributo "%s" está repetido.', $name[ProductTexts::FALLBACK]));
            }
            $seen[$key] = true;
            $clean[] = ['name' => $name, 'value' => $value];
        }

        return new self($clean);
    }

    /** @return list<array{name: array<string, string>, value: array<string, string>}> */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Los atributos en un idioma: de cada nombre y de cada valor, el texto de ese idioma o, si no está traducido, el español. Sin
     * idioma, todos los textos.
     *
     * @return list<array{name: array<string, string>, value: array<string, string>}>
     */
    public function forLanguage(?string $language): array
    {
        if ($language === null || $language === '') {
            return $this->items;
        }

        return array_map(static fn (array $item): array => [
            'name' => self::pick($item['name'], $language),
            'value' => self::pick($item['value'], $language),
        ], $this->items);
    }

    /**
     * @param array<string, string> $texts
     *
     * @return array<string, string>
     */
    private static function pick(array $texts, string $language): array
    {
        return isset($texts[$language]) ? [$language => $texts[$language]] : [ProductTexts::FALLBACK => $texts[ProductTexts::FALLBACK]];
    }

    /**
     * @return array<string, string> por idioma, con el español siempre presente
     */
    private static function texts(mixed $byLanguage, string $what, int $max, int $number): array
    {
        if (!is_array($byLanguage)) {
            throw new InvalidProduct(sprintf('El atributo %d necesita su %s en español.', $number, $what));
        }
        $clean = [];
        foreach ($byLanguage as $language => $text) {
            if (!in_array($language, ProductTexts::LANGUAGES, true)) {
                throw new InvalidProduct(sprintf('El idioma "%s" no está admitido (%s).', (string) $language, implode(', ', ProductTexts::LANGUAGES)));
            }
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > $max) {
                throw new InvalidProduct(sprintf('El %s de un atributo admite como mucho %d caracteres.', $what, $max));
            }
            $clean[$language] = $text;
        }
        if (!isset($clean[ProductTexts::FALLBACK])) {
            throw new InvalidProduct(sprintf('El atributo %d necesita su %s en español.', $number, $what));
        }

        return $clean;
    }

    private static function fold(string $text): string
    {
        $plain = mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text));

        return trim((string) preg_replace('/\s+/', ' ', $plain));
    }
}
