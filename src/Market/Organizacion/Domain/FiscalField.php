<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Definición de un dato fiscal que se pide para facturar en un país (COMR-270). Inmutable: editar un
 * campo es sustituirlo por otro con la misma clave (FiscalProfile::replaceFields()).
 *
 * `pattern` es una expresión regular SIN delimitadores; se valida al construir.
 */
final readonly class FiscalField
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    /**
     * @param list<string> $options valores admitidos; solo para el tipo SELECT
     */
    public function __construct(
        public string $key,
        public string $label,
        public FiscalFieldType $type,
        public bool $required = false,
        public ?string $pattern = null,
        public ?string $helpText = null,
        public array $options = [],
        public bool $active = true,
        public int $position = 0,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidFiscalProfile(sprintf('La clave "%s" no es válida: minúsculas, números y guion bajo, empezando por letra (máx. 60).', $key));
        }
        if (trim($label) === '') {
            throw new InvalidFiscalProfile(sprintf('El campo "%s" necesita una etiqueta.', $key));
        }
        if ($pattern !== null && $pattern !== '' && @preg_match(self::delimit($pattern), '') === false) {
            throw new InvalidFiscalProfile(sprintf('El formato del campo "%s" no es una expresión regular válida.', $key));
        }
        if ($type === FiscalFieldType::SELECT && $options === []) {
            throw new InvalidFiscalProfile(sprintf('El campo "%s" es de selección y necesita al menos una opción.', $key));
        }
        if ($type !== FiscalFieldType::SELECT && $options !== []) {
            throw new InvalidFiscalProfile(sprintf('Solo un campo de selección puede tener opciones ("%s").', $key));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            type: FiscalFieldType::tryFrom((string) ($data['type'] ?? '')) ?? throw new InvalidFiscalProfile(sprintf('Tipo de campo desconocido en "%s".', (string) ($data['key'] ?? ''))),
            required: (bool) ($data['required'] ?? false),
            pattern: isset($data['pattern']) && $data['pattern'] !== '' ? (string) $data['pattern'] : null,
            helpText: isset($data['helpText']) && $data['helpText'] !== '' ? (string) $data['helpText'] : null,
            options: array_values(array_map('strval', (array) ($data['options'] ?? []))),
            active: (bool) ($data['active'] ?? true),
            position: (int) ($data['position'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'pattern' => $this->pattern,
            'helpText' => $this->helpText,
            'options' => $this->options,
            'active' => $this->active,
            'position' => $this->position,
        ];
    }

    public function deactivated(): self
    {
        return new self($this->key, $this->label, $this->type, $this->required, $this->pattern, $this->helpText, $this->options, false, $this->position);
    }

    /**
     * Código de error si el valor no cumple el campo (`type`, `option` o `format`), null si es correcto.
     * El valor ya viene sin vacíos: de eso se encarga FiscalProfile::validate().
     */
    public function errorFor(mixed $value): ?string
    {
        if (!is_scalar($value) || is_bool($value)) {
            return 'type';
        }
        $text = trim((string) $value);

        switch ($this->type) {
            case FiscalFieldType::NUMBER:
                if (!is_numeric($text)) {
                    return 'type';
                }
                break;
            case FiscalFieldType::DATE:
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);
                if ($date === false || $date->format('Y-m-d') !== $text) {
                    return 'type';
                }
                break;
            case FiscalFieldType::SELECT:
                if (!in_array($text, $this->options, true)) {
                    return 'option';
                }
                break;
            case FiscalFieldType::TEXT:
                break;
        }

        if ($this->pattern !== null && $this->pattern !== '' && preg_match(self::delimit($this->pattern), $text) !== 1) {
            return 'format';
        }

        return null;
    }

    private static function delimit(string $pattern): string
    {
        return '~' . str_replace('~', '\~', $pattern) . '~u';
    }
}
