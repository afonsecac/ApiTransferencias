<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Un centro de costo por crear, antes de tener unidad. Normaliza y valida el código y el nombre.
 */
final readonly class CostCenterDraft
{
    public const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9_-]{0,29}$/';

    public string $code;
    public string $name;

    public function __construct(string $code, string $name, public bool $principal = false)
    {
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
}
