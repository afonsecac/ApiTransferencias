<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Domain;

/**
 * Los textos del producto en varios idiomas (es y en al inicio, ampliable). El español es obligatorio y es el idioma de
 * respaldo: si falta la traducción del idioma pedido se devuelve el español.
 */
final readonly class ProductTexts
{
    /** @var list<string> */
    public const LANGUAGES = ['es', 'en'];
    public const FALLBACK = 'es';
    public const NAME_MAX_LENGTH = 200;
    public const DESCRIPTION_MAX_LENGTH = 2000;

    /**
     * @param array<string, array{name: string, description: string|null}> $texts
     */
    private function __construct(private array $texts)
    {
    }

    /**
     * @param array<string, array{name?: string|null, description?: string|null}> $texts por código de idioma
     * @throws InvalidProduct
     */
    public static function fromArray(array $texts): self
    {
        $clean = [];
        foreach ($texts as $language => $text) {
            if (!in_array($language, self::LANGUAGES, true)) {
                throw new InvalidProduct(sprintf('El idioma "%s" no está admitido (%s).', $language, implode(', ', self::LANGUAGES)));
            }
            $name = trim((string) ($text['name'] ?? ''));
            if ($name === '') {
                throw new InvalidProduct(sprintf('El nombre en "%s" no puede estar vacío.', $language));
            }
            if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
                throw new InvalidProduct(sprintf('El nombre admite como mucho %d caracteres.', self::NAME_MAX_LENGTH));
            }
            $description = isset($text['description']) ? trim((string) $text['description']) : null;
            if ($description !== null && mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
                throw new InvalidProduct(sprintf('La descripción admite como mucho %d caracteres.', self::DESCRIPTION_MAX_LENGTH));
            }
            $clean[$language] = ['name' => $name, 'description' => $description === '' ? null : $description];
        }
        if (!isset($clean[self::FALLBACK])) {
            throw new InvalidProduct('El nombre en español es obligatorio.');
        }
        // Siempre en el orden de LANGUAGES, para que lo guardado sea estable.
        $ordered = [];
        foreach (self::LANGUAGES as $language) {
            if (isset($clean[$language])) {
                $ordered[$language] = $clean[$language];
            }
        }

        return new self($ordered);
    }

    /**
     * @return array<string, array{name: string, description: string|null}>
     */
    public function all(): array
    {
        return $this->texts;
    }

    /**
     * Sin idioma, todos; con idioma, solo ese (o el español si no hay traducción o el idioma no se conoce).
     *
     * @return array<string, array{name: string, description: string|null}>
     */
    public function forLanguage(?string $language): array
    {
        if ($language === null) {
            return $this->texts;
        }
        $language = isset($this->texts[$language]) ? $language : self::FALLBACK;

        return [$language => $this->texts[$language]];
    }
}
