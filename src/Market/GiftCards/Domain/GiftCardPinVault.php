<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Domain;

/**
 * Todo lo que toca el PIN: lo genera (no adivinable), lo cifra para poder mostrarlo a quien tiene permiso y calcula la huella con
 * la que se busca al canjear. El PIN en claro no se guarda nunca.
 */
interface GiftCardPinVault
{
    public function generate(): string;

    public function encrypt(string $pin): string;

    public function decrypt(string $cipher): string;

    /** Huella estable del PIN (sin guiones ni espacios, sin distinguir mayúsculas) para localizar la tarjeta sin descifrar. */
    public function hash(string $pin): string;
}
