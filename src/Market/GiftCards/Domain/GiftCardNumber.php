<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

/**
 * El identificador de una gift card: un número de 16 dígitos como el de una tarjeta de crédito, con su dígito de control (Luhn) para
 * detectar erratas al teclearlo. Empieza por 9 (el prefijo de la industria que reserva ISO/IEC 7812 para asignación propia: no
 * choca con ningún emisor real). Los 14 dígitos siguientes son aleatorios, no una secuencia. NO es secreto: el secreto es el PIN.
 */
final class GiftCardNumber
{
    public const LENGTH = 16;
    private const PREFIX = '9';

    public static function generate(): string
    {
        $body = self::PREFIX;
        for ($i = 1; $i < self::LENGTH - 1; ++$i) {
            $body .= (string) random_int(0, 9);
        }

        return $body . self::checkDigit($body);
    }

    public static function isValid(string $number): bool
    {
        return preg_match('/^\d{' . self::LENGTH . '}$/', $number) === 1 && self::passesLuhn($number);
    }

    public static function passesLuhn(string $number): bool
    {
        if (preg_match('/^\d{12,19}$/', $number) !== 1) {
            return false;
        }
        $sum = 0;
        $double = false;
        for ($i = strlen($number) - 1; $i >= 0; --$i) {
            $digit = (int) $number[$i];
            if ($double) {
                $digit *= 2;
                $digit -= $digit > 9 ? 9 : 0;
            }
            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    /** «4111 1111 1111 1111»: en grupos de cuatro, como se lee en una tarjeta. */
    public static function format(string $number): string
    {
        return trim(chunk_split(self::normalize($number), 4, ' '));
    }

    /** Solo los dígitos: acepta lo que se copia con espacios, guiones o puntos. */
    public static function normalize(string $input): string
    {
        return (string) preg_replace('/\D/', '', $input);
    }

    private static function checkDigit(string $body): string
    {
        // El dígito de control es el que hace que el número completo pase Luhn.
        for ($d = 0; $d <= 9; ++$d) {
            if (self::passesLuhn($body . $d)) {
                return (string) $d;
            }
        }

        throw new \LogicException('Siempre hay un dígito de control Luhn.');
    }
}
