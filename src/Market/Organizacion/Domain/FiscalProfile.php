<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Registro fiscal de un país (COMR-270): qué datos se piden para facturar a una unidad organizativa de
 * ese país y con qué formato. Cada país tiene el suyo; el código `ZZ` (reservado por ISO para uso
 * propio) es el registro genérico de los países sin uno propio.
 *
 * Los campos se guardan como JSON dentro del registro: se cambian siempre juntos y son pocos. Un campo
 * nunca se borra, solo se desactiva, porque las unidades ya guardadas pueden tener datos en él.
 */
#[ORM\Entity]
#[ORM\Table(name: 'market_fiscal_profile')]
class FiscalProfile
{
    public const GENERIC_CODE = 'ZZ';

    #[ORM\Id]
    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $fields = [];

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param list<FiscalField> $fields
     */
    public function __construct(string $countryCode, string $name, array $fields)
    {
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new InvalidFiscalProfile(sprintf('El código de país "%s" no es válido: dos letras mayúsculas.', $countryCode));
        }
        if (trim($name) === '') {
            throw new InvalidFiscalProfile('El registro fiscal necesita un nombre.');
        }

        $this->countryCode = $countryCode;
        $this->name = trim($name);
        $this->setFields($fields);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function generic(): self
    {
        return new self(self::GENERIC_CODE, 'Resto de países', []);
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidFiscalProfile('El registro fiscal necesita un nombre.');
        }
        $this->name = trim($name);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<FiscalField> ordenados por posición
     */
    public function fields(): array
    {
        $fields = array_map(static fn (array $data) => FiscalField::fromArray($data), $this->fields);
        usort($fields, static fn (FiscalField $a, FiscalField $b) => $a->position <=> $b->position);

        return $fields;
    }

    /**
     * Sustituye la lista de campos (alta, edición y desactivación). No admite quitar uno que ya existía.
     *
     * @param list<FiscalField> $fields
     */
    public function replaceFields(array $fields): void
    {
        $newKeys = array_map(static fn (FiscalField $f) => $f->key, $fields);
        foreach ($this->fields as $existing) {
            if (!in_array($existing['key'], $newKeys, true)) {
                throw FiscalFieldCannotBeRemoved::forKey((string) $existing['key']);
            }
        }

        $this->setFields($fields);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Valida los datos fiscales de una unidad contra los campos ACTIVOS del registro. Las claves que el
     * registro no conoce o tiene desactivadas se ignoran: son datos ya guardados que no se pierden.
     *
     * @param array<string, mixed> $values
     * @return array<string, string> código de error (`required`, `type`, `option` o `format`) por clave
     */
    public function validate(array $values): array
    {
        $errors = [];
        foreach ($this->fields() as $field) {
            if (!$field->active) {
                continue;
            }

            $value = $values[$field->key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                if ($field->required) {
                    $errors[$field->key] = 'required';
                }
                continue;
            }

            $error = $field->errorFor($value);
            if ($error !== null) {
                $errors[$field->key] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param list<FiscalField> $fields
     */
    private function setFields(array $fields): void
    {
        $keys = [];
        foreach ($fields as $field) {
            if (isset($keys[$field->key])) {
                throw new InvalidFiscalProfile(sprintf('La clave de campo "%s" está repetida.', $field->key));
            }
            $keys[$field->key] = true;
        }

        $this->fields = array_map(static fn (FiscalField $f) => $f->toArray(), $fields);
    }
}
