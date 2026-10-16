<?php

declare(strict_types=1);

namespace App\Market\Geografia\Domain;

/**
 * Validaciones comunes de provincias y municipios.
 */
final class Code
{
    public const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public static function validate(string $code): string
    {
        if (strlen($code) > 60 || preg_match(self::PATTERN, $code) !== 1) {
            throw new InvalidGeography(sprintf('El código "%s" no es válido: minúsculas, números y guiones sueltos, máximo 60.', $code));
        }

        return $code;
    }

    public static function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidGeography('El nombre es obligatorio y admite hasta 120 caracteres.');
        }

        return $name;
    }
}
