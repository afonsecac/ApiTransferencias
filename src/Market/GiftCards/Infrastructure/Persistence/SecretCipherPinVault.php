<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Persistence;

use App\Market\GiftCards\Domain\GiftCardPinVault;
use App\Security\SecretCipher;

/**
 * El baúl de PIN: genera PIN de 16 caracteres (≈78 bits de aleatoriedad criptográfica, sin caracteres ambiguos), los cifra con el
 * mismo cifrado autenticado (AES-256-GCM) que los secretos 2FA y calcula una huella HMAC-SHA256 con una clave propia derivada del
 * secreto de la aplicación. Cambiar APP_SECRET impide descifrar los PIN ya emitidos y localizarlos al canjear.
 */
final class SecretCipherPinVault implements GiftCardPinVault
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
    private const GROUPS = 4;
    private const GROUP_LENGTH = 4;

    private readonly string $hashKey;

    public function __construct(private readonly SecretCipher $cipher, string $appSecret)
    {
        $this->hashKey = hash_hkdf('sha256', $appSecret, 32, 'gift-card-pin-hash');
    }

    public function generate(): string
    {
        $groups = [];
        for ($g = 0; $g < self::GROUPS; ++$g) {
            $group = '';
            for ($c = 0; $c < self::GROUP_LENGTH; ++$c) {
                $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $group;
        }

        return implode('-', $groups);
    }

    public function encrypt(string $pin): string
    {
        return $this->cipher->encrypt($pin);
    }

    public function decrypt(string $cipher): string
    {
        return $this->cipher->decrypt($cipher);
    }

    public function hash(string $pin): string
    {
        return hash_hmac('sha256', strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $pin)), $this->hashKey);
    }
}
