<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Centro de costo de una unidad organizativa (COMR-271). Se identifica por su código dentro de la unidad.
 * Inmutable: cambiar algo es sustituirlo (OrgUnit lo hace). Un centro no se borra, se desactiva: lo que
 * se impute a él después debe seguir teniendo a quién apuntar.
 */
final readonly class CostCenter
{
    private const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9_-]{0,29}$/';

    public string $code;
    public string $name;

    public function __construct(
        string $code,
        string $name,
        public bool $principal = false,
        public bool $active = true,
    ) {
        $code = strtoupper(trim($code));
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new InvalidOrgUnit(sprintf('El código "%s" del centro de costo no es válido: letras, números, guion y guion bajo (máx. 30).', $code));
        }
        if (trim($name) === '') {
            throw new InvalidOrgUnit(sprintf('El centro de costo "%s" necesita un nombre.', $code));
        }

        $this->code = $code;
        $this->name = trim($name);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['code'] ?? ''),
            (string) ($data['name'] ?? ''),
            (bool) ($data['principal'] ?? false),
            (bool) ($data['active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'principal' => $this->principal, 'active' => $this->active];
    }

    public function withName(string $name): self
    {
        return new self($this->code, $name, $this->principal, $this->active);
    }

    public function withPrincipal(bool $principal): self
    {
        return new self($this->code, $this->name, $principal, $this->active);
    }

    public function withActive(bool $active): self
    {
        return new self($this->code, $this->name, $this->principal, $active);
    }
}
