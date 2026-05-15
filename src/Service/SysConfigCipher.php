<?php

namespace App\Service;

final class SysConfigCipher
{
    public static function encrypt(string $plaintext, string $hexKey): string
    {
        if (strlen($hexKey) !== 64 || !ctype_xdigit($hexKey)) {
            throw new \RuntimeException('SYS_CONFIG_ENCRYPTION_KEY debe ser exactamente 64 caracteres hexadecimales (openssl rand -hex 32)');
        }
        $key = sodium_hex2bin($hexKey);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(string $encoded, string $hexKey): string
    {
        if (strlen($hexKey) !== 64 || !ctype_xdigit($hexKey)) {
            throw new \RuntimeException('SYS_CONFIG_ENCRYPTION_KEY debe ser exactamente 64 caracteres hexadecimales (openssl rand -hex 32)');
        }
        $key = sodium_hex2bin($hexKey);
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('SysConfig: datos cifrados inválidos');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new \RuntimeException('SysConfig: falló el descifrado — clave incorrecta o dato corrompido');
        }
        return $plaintext;
    }
}
